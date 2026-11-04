<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Promotion;

use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Entity\Promotion;
use RomainGenin\AstraCheckoutBundle\Exception\CartException;
use RomainGenin\AstraCheckoutBundle\Pricing\MoneyFormatter;
use RomainGenin\AstraCheckoutBundle\Repository\OrderRepository;

/** Tells precisely why a code cannot be used (expired, minimum not reached…), never just "invalid". */
final class CouponValidator
{
    public function __construct(
        private readonly PromotionProvider $provider,
        private readonly PromotionCalculator $calculator,
        private readonly OrderRepository $orders,
        private readonly MoneyFormatter $money,
        private readonly CustomerInfo $customers,
    ) {
    }

    /** @throws CartException */
    public function validate(?Promotion $promotion, Cart $cart, ?string $email = null): Promotion
    {
        $now = $this->provider->now();
        if (null === $promotion || $promotion->isAutomatic() || !$promotion->isEnabled()) {
            throw new CartException('coupon.error.unknown');
        }
        if (null !== $promotion->getStartsAt() && $promotion->getStartsAt() > $now) {
            throw new CartException('coupon.error.not_started', ['%date%' => $promotion->getStartsAt()->format('d/m/Y')]);
        }
        if (null !== $promotion->getEndsAt() && $promotion->getEndsAt() <= $now) {
            throw new CartException('coupon.error.expired');
        }
        if ($promotion->isUsedUp()) {
            throw new CartException('coupon.error.usage_limit');
        }

        $subtotal = 0;
        foreach ($cart->getItems() as $item) {
            $subtotal += $item->getTotal();
        }
        if (null !== $promotion->getMinSubtotal() && $subtotal < $promotion->getMinSubtotal()) {
            throw new CartException('coupon.error.min_subtotal', [
                '%minimum%' => $this->money->format($promotion->getMinSubtotal()),
                '%remaining%' => $this->money->format($promotion->getMinSubtotal() - $subtotal),
            ]);
        }
        if (!$this->calculator->isApplicable($promotion, $cart)) {
            throw new CartException('coupon.error.not_applicable');
        }

        $email ??= (null !== $cart->getCustomer() ? $this->customers->email($cart->getCustomer()) : null) ?? $cart->getEmail();
        if (null !== $promotion->getUsageLimitPerCustomer() && (null !== $cart->getCustomer() || null !== $email)
            && $this->orders->countPromotionUses((string) $promotion->getCode(), $cart->getCustomer(), $email) >= $promotion->getUsageLimitPerCustomer()) {
            throw new CartException('coupon.error.customer_limit');
        }

        return $promotion;
    }
}
