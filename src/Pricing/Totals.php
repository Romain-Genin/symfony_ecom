<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Pricing;

use StudioAstra\AstraCheckoutBundle\Promotion\AppliedPromotions;

/** Totals of a cart, in minor units. */
final class Totals
{
    public readonly AppliedPromotions $promotions;

    /**
     * @param ?int            $shipping      null while no shipping method is chosen
     * @param array<int, int> $taxByRate     basis points => tax amount (VAT breakdown)
     * @param array<int, int> $itemTaxes     tax amount per cart line
     * @param int             $discount      promotions total (lines only, free shipping is in $shipping)
     */
    public function __construct(
        public readonly int $subtotal,
        public readonly ?int $shipping,
        public readonly int $tax,
        public readonly int $total,
        public readonly int $itemCount,
        public readonly bool $pricesIncludeTax,
        public readonly array $taxByRate = [],
        public readonly array $itemTaxes = [],
        public readonly int $discount = 0,
        ?AppliedPromotions $promotions = null,
    ) {
        $this->promotions = $promotions ?? new AppliedPromotions();
    }

    public function getNetSubtotal(): int
    {
        return $this->subtotal - $this->discount;
    }

    /** @return array{subtotal: int, discount: int, shipping: int, tax: int, total: int} */
    public function toArray(): array
    {
        return ['subtotal' => $this->subtotal, 'discount' => $this->discount, 'shipping' => $this->shipping ?? 0, 'tax' => $this->tax, 'total' => $this->total];
    }
}
