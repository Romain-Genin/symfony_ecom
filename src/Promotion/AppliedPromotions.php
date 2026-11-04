<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Promotion;

/** Result of the promotion engine for a cart. */
final class AppliedPromotions
{
    /**
     * @param list<AppliedDiscount> $discounts
     * @param array<int, int>       $itemDiscounts discount per cart line (keyed like Totals::$itemTaxes)
     */
    public function __construct(
        public readonly array $discounts = [],
        public readonly array $itemDiscounts = [],
        public readonly bool $freeShipping = false,
    ) {
    }

    public function total(): int
    {
        return array_sum($this->itemDiscounts);
    }

    public function hasCode(string $code): bool
    {
        foreach ($this->discounts as $discount) {
            if ($discount->code === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> */
    public function promotionIds(): array
    {
        return array_map(static fn (AppliedDiscount $d): int => $d->promotionId, $this->discounts);
    }
}
