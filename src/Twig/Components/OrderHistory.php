<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Repository\OrderRepository;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/** The customer's orders, newest first, paginated with ?page=: <twig:Astra:OrderHistory perPage="10" /> */
#[AsTwigComponent('Astra:OrderHistory', template: '@AstraCheckout/components/Astra/OrderHistory.html.twig')]
final class OrderHistory
{
    use CustomerAware;

    public int $perPage = 10;
    public bool $pagination = true;
    public int $headingLevel = 2;
    /** @var list<Order> */
    public array $orders = [];
    public int $total = 0;
    public int $currentPage = 1;
    public int $pageCount = 1;

    public function __construct(
        private readonly OrderRepository $repository,
        private readonly Security $security,
        private readonly CustomerInfo $customers,
        private readonly ShopSettings $settings,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[PostMount]
    public function load(): void
    {
        $customer = $this->requireCustomer($this->security, $this->customers, $this->settings);
        $this->total = $this->repository->countForCustomer($customer);
        $this->pageCount = max(1, (int) ceil($this->total / max(1, $this->perPage)));
        $this->currentPage = $this->pagination ? max(1, min($this->pageCount, $this->requestStack->getCurrentRequest()?->query->getInt('page', 1) ?? 1)) : 1;
        $this->orders = $this->repository->findForCustomer($customer, $this->currentPage, $this->perPage);
    }
}
