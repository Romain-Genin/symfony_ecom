<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use StudioAstra\AstraCheckoutBundle\Entity\Order;
use StudioAstra\AstraCheckoutBundle\Http\PageResponse;
use StudioAstra\AstraCheckoutBundle\Payment\PaymentMethodRegistry;
use StudioAstra\AstraCheckoutBundle\Repository\OrderRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * "Thank you" page block: <twig:Astra:OrderConfirmation /> on a page whose route has a {token}
 * parameter (astra_checkout.pages.confirmation). Answers 404 for an unknown token.
 */
#[AsTwigComponent('Astra:OrderConfirmation', template: '@AstraCheckout/components/Astra/OrderConfirmation.html.twig')]
final class OrderConfirmation
{
    public ?string $token = null;
    public ?Order $order = null;
    public ?string $instructions = null;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly PaymentMethodRegistry $payments,
        private readonly RequestStack $requestStack,
        private readonly PageResponse $page,
    ) {
    }

    #[PostMount]
    public function load(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $token = $this->token ?? $request?->attributes->get('token') ?? $request?->query->get('token');
        if (null === $this->order && \is_string($token) && preg_match('/^[a-f0-9]{48}$/', $token)) {
            $this->order = $this->orders->findOneWithItems(['token' => $token]);
        }
        if (null === $this->order) {
            $this->page->setStatus(404);

            return;
        }
        $this->instructions = $this->payments->get($this->order->getPaymentMethodCode())?->getInstructions($this->order);
    }
}
