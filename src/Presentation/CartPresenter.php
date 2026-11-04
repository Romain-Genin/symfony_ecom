<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Presentation;

use RomainGenin\AstraCheckoutBundle\Cart\CartContext;
use RomainGenin\AstraCheckoutBundle\Cart\CartManager;
use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Pricing\MoneyFormatter;
use RomainGenin\AstraCheckoutBundle\Pricing\TotalsCalculator;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

final class CartPresenter
{
    public function __construct(
        private readonly CartContext $context,
        private readonly CartManager $manager,
        private readonly TotalsCalculator $totals,
        private readonly ShippingMethodRegistry $shipping,
        private readonly MoneyFormatter $money,
        private readonly RequestStack $requestStack,
    ) {
    }

    /** @return array{cart: ?Cart, totals: ?\RomainGenin\AstraCheckoutBundle\Pricing\Totals, notices: list<array{key: string, parameters: array<string, mixed>}>, free_shipping: ?array, coupon_attempt: ?string, coupon_error: ?string} */
    public function view(): array
    {
        $cart = $this->context->getCart();
        $notices = null !== $cart ? $this->manager->revalidate($cart) : [];
        $request = $this->requestStack->getCurrentRequest();
        $session = null !== $request && $request->hasPreviousSession() ? $request->getSession() : null;

        return [
            'cart' => $cart,
            'totals' => null !== $cart ? $this->totals->calculate($cart, '') : null,
            'notices' => $notices,
            'free_shipping' => $this->freeShipping($cart),
            'coupon_attempt' => $session?->remove('_astra_coupon_attempt'),
            'coupon_error' => $session?->remove('_astra_coupon_error'),
        ];
    }

    /** @return array{threshold: int, remaining: int, remainingFormatted: string, progress: int}|null */
    public function freeShipping(?Cart $cart): ?array
    {
        $threshold = $this->shipping->getLowestFreeShippingThreshold();
        if (null === $threshold) {
            return null;
        }
        $subtotal = null === $cart ? 0 : $this->totals->calculate($cart, '')->getNetSubtotal();
        $remaining = max(0, $threshold - $subtotal);

        return [
            'threshold' => $threshold,
            'remaining' => $remaining,
            'remainingFormatted' => $this->money->format($remaining),
            'progress' => (int) min(100, round($subtotal * 100 / max(1, $threshold))),
        ];
    }
}
