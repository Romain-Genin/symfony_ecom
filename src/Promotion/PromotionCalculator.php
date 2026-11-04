<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Promotion;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Entity\CartItem;
use RomainGenin\AstraCheckoutBundle\Entity\Promotion;
use RomainGenin\AstraCheckoutBundle\Enum\PromotionType;

/**
 * Promotion engine.
 *
 * - Automatic promotions and the coupon code of the cart are candidates.
 * - Non-exclusive promotions stack, by priority, each one applying to what remains of the lines
 *   (a line can never go below zero).
 * - An exclusive promotion applies alone; the customer always gets the best offer.
 * - Discounts are allocated per line (largest remainder method, to the cent) so that the VAT is
 *   computed on the discounted line amounts.
 */
final class PromotionCalculator
{
    public function __construct(private readonly PromotionProvider $provider)
    {
    }

    /**
     * @param int $shippingCost shipping before promotions, used to compare a free-shipping offer with a discount
     */
    public function calculate(Cart $cart, int $shippingCost = 0): AppliedPromotions
    {
        $lines = $this->lines($cart);
        if ([] === $lines) {
            return new AppliedPromotions();
        }
        $subtotal = array_sum(array_column($lines, 'amount'));

        $candidates = $this->provider->getActiveAutomatic();
        if (null !== ($code = $cart->getCouponCode()) && null !== ($coupon = $this->provider->findCoupon($code)) && $coupon->isActiveAt($this->provider->now())) {
            $candidates[] = $coupon;
        }
        $candidates = array_values(array_filter($candidates, static fn (Promotion $p): bool => null === $p->getMinSubtotal() || $subtotal >= $p->getMinSubtotal()));
        usort($candidates, static fn (Promotion $a, Promotion $b): int => [$b->getPriority(), $a->getId() ?? 0] <=> [$a->getPriority(), $b->getId() ?? 0]);
        if ([] === $candidates) {
            return new AppliedPromotions();
        }

        $options = [$this->stack(array_values(array_filter($candidates, static fn (Promotion $p): bool => !$p->isExclusive())), $lines)];
        foreach ($candidates as $promotion) {
            if ($promotion->isExclusive()) {
                $options[] = $this->stack([$promotion], $lines);
            }
        }

        $best = null;
        $bestValue = -1;
        foreach ($options as $option) {
            $value = $option->total() + ($option->freeShipping ? $shippingCost : 0);
            if ($value > $bestValue || ($value === $bestValue && \count($option->discounts) > \count($best?->discounts ?? []))) {
                $best = $option;
                $bestValue = $value;
            }
        }

        return $best;
    }

    /** Whether at least one line of the cart is eligible. */
    public function isApplicable(Promotion $promotion, Cart $cart): bool
    {
        foreach ($this->lines($cart) as $line) {
            if ($this->eligible($promotion, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Promotion> $promotions
     * @param array<int, array{item: CartItem, amount: int, onSale: bool}> $lines
     */
    private function stack(array $promotions, array $lines): AppliedPromotions
    {
        $remaining = array_map(static fn (array $l): int => $l['amount'], $lines);
        $itemDiscounts = array_fill_keys(array_keys($lines), 0);
        $discounts = [];
        $freeShipping = false;

        foreach ($promotions as $promotion) {
            $eligible = array_filter($remaining, fn (int $amount, int $key): bool => $amount > 0 && $this->eligible($promotion, $lines[$key]), \ARRAY_FILTER_USE_BOTH);
            if ([] === $eligible) {
                continue;
            }

            $allocation = match ($promotion->getType()) {
                PromotionType::Percentage => $this->percentage($eligible, $promotion),
                PromotionType::FixedAmount => self::allocate($eligible, min($promotion->getValue(), array_sum($eligible))),
                PromotionType::FreeShipping => [],
            };
            $amount = array_sum($allocation);
            $isFreeShipping = PromotionType::FreeShipping === $promotion->getType();
            if (0 === $amount && !$isFreeShipping) {
                continue;
            }
            foreach ($allocation as $key => $value) {
                $remaining[$key] -= $value;
                $itemDiscounts[$key] += $value;
            }
            $freeShipping = $freeShipping || $isFreeShipping;
            $discounts[] = new AppliedDiscount((int) $promotion->getId(), $promotion->getName(), $promotion->getDisplayLabel(), $promotion->getCode(), $amount, $isFreeShipping);
        }

        return new AppliedPromotions($discounts, array_filter($itemDiscounts), $freeShipping);
    }

    /**
     * @param array<int, int> $eligible
     *
     * @return array<int, int>
     */
    private function percentage(array $eligible, Promotion $promotion): array
    {
        $total = (int) round(array_sum($eligible) * $promotion->getValue() / 10000, 0, \PHP_ROUND_HALF_UP);
        if (null !== $promotion->getMaxDiscount()) {
            $total = min($total, $promotion->getMaxDiscount());
        }

        return self::allocate($eligible, $total);
    }

    /**
     * Spreads $total over the lines proportionally to their amounts, to the cent (largest remainder).
     *
     * @param array<int, int> $amounts
     *
     * @return array<int, int>
     */
    public static function allocate(array $amounts, int $total): array
    {
        $sum = array_sum($amounts);
        if ($sum <= 0 || $total <= 0) {
            return array_map(static fn (): int => 0, $amounts);
        }
        $shares = [];
        $remainders = [];
        foreach ($amounts as $key => $amount) {
            $exact = $total * $amount / $sum;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $shares[$key];
        }
        arsort($remainders);
        $left = $total - array_sum($shares);
        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            ++$shares[$key];
            --$left;
        }

        return $shares;
    }

    /** @param array{item: CartItem, amount: int, onSale: bool} $line */
    private function eligible(Promotion $promotion, array $line): bool
    {
        if ($promotion->isExcludeSaleItems() && $line['onSale']) {
            return false;
        }

        return $this->provider->appliesTo($promotion, $line['item']->getProduct());
    }

    /** @return array<int, array{item: CartItem, amount: int, onSale: bool}> */
    private function lines(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->getItems() as $item) {
            $variant = $item->getVariant();
            $lines[self::key($item)] = [
                'item' => $item,
                'amount' => $item->getTotal(),
                'onSale' => null !== $variant->getCompareAtPrice() && $variant->getCompareAtPrice() > $variant->getPrice(),
            ];
        }

        return $lines;
    }

    public static function key(CartItem $item): int
    {
        return $item->getId() ?? spl_object_id($item);
    }
}
