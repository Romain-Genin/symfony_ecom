<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;
use RomainGenin\AstraCheckoutBundle\Http\PageResponse;
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodRegistry;
use RomainGenin\AstraCheckoutBundle\Repository\OrderRepository;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/** One order of the logged-in customer (route parameter {number}): tracking, items, addresses, cancellation. */
#[AsTwigComponent('Astra:OrderDetail', template: '@AstraCheckout/components/Astra/OrderDetail.html.twig')]
final class OrderDetail
{
    use CustomerAware;

    public ?string $number = null;
    public ?Order $order = null;
    public ?string $instructions = null;
    public bool $heading = true;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly PaymentMethodRegistry $payments,
        private readonly Security $security,
        private readonly CustomerInfo $customers,
        private readonly ShopSettings $settings,
        private readonly RequestStack $requestStack,
        private readonly PageResponse $page,
    ) {
    }

    #[PostMount]
    public function load(): void
    {
        $customer = $this->requireCustomer($this->security, $this->customers, $this->settings);
        $number = $this->number ?? $this->requestStack->getCurrentRequest()?->attributes->get('number');
        $this->order = \is_string($number) ? $this->orders->findOneWithItems(['number' => $number, 'customer' => $customer]) : null;
        if (null === $this->order) {
            $this->page->setStatus(404);

            return;
        }
        if (OrderStatus::PendingPayment === $this->order->getStatus()) {
            $this->instructions = $this->payments->get($this->order->getPaymentMethodCode())?->getInstructions($this->order);
        }
    }

    /** @return list<OrderStatus> */
    public function getTimeline(): array
    {
        return [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered];
    }
}
