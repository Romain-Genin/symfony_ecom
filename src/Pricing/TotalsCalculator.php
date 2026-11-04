<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Pricing;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Promotion\PromotionCalculator;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;
use RomainGenin\AstraCheckoutBundle\ShopSettings;

/**
 * Computes cart totals. Promotions are allocated per line first, then taxes are computed per
 * line on the discounted amounts (rounded half-up), the way EU invoices are issued; shipping
 * is taxed at astra_checkout.shipping.tax_rate.
 */
final class TotalsCalculator
{
    public function __construct(
        private readonly ShopSettings $settings,
        private readonly ShippingMethodRegistry $shipping,
        private readonly ?PromotionCalculator $promotions = null,
    ) {
    }

    /**
     * @param ?string $shippingMethodCode null = the cart's method, '' = none (cart page)
     */
    public function calculate(Cart $cart, ?string $shippingMethodCode = null): Totals
    {
        $inclusive = $this->settings->pricesIncludeTax();

        $subtotal = 0;
        foreach ($cart->getItems() as $item) {
            $subtotal += $item->getTotal();
        }

        $code = $shippingMethodCode ?? $cart->getShippingMethod();
        $method = null !== $code && '' !== $code ? $this->shipping->get($code) : null;
        if (null !== $method && !$method->supports($cart)) {
            $method = null;
        }

        $applied = $this->promotions?->calculate($cart, null !== $method ? $method->calculate($cart, $subtotal) : 0);
        $itemDiscounts = $applied?->itemDiscounts ?? [];
        $discount = array_sum($itemDiscounts);

        $tax = 0;
        $taxByRate = [];
        $itemTaxes = [];
        foreach ($cart->getItems() as $item) {
            $key = PromotionCalculator::key($item);
            $net = $item->getTotal() - ($itemDiscounts[$key] ?? 0);
            $rate = $item->getProduct()->getTaxRate();
            $lineTax = self::taxOf($net, $rate, $inclusive);
            $tax += $lineTax;
            $taxByRate[$rate] = ($taxByRate[$rate] ?? 0) + $lineTax;
            $itemTaxes[$key] = $lineTax;
        }

        $shipping = null;
        if (null !== $method) {
            $shipping = ($applied?->freeShipping ?? false) ? 0 : $method->calculate($cart, $subtotal - $discount);
            $rate = $this->settings->getShippingTaxRate();
            $shippingTax = self::taxOf($shipping, $rate, $inclusive);
            $tax += $shippingTax;
            if ($shippingTax > 0) {
                $taxByRate[$rate] = ($taxByRate[$rate] ?? 0) + $shippingTax;
            }
        }

        $total = $subtotal - $discount + ($shipping ?? 0) + ($inclusive ? 0 : $tax);
        ksort($taxByRate);

        return new Totals($subtotal, $shipping, $tax, $total, $cart->getItemCount(), $inclusive, array_filter($taxByRate), $itemTaxes, $discount, $applied);
    }

    /**
     * @param int $rate basis points (2000 = 20 %)
     */
    public static function taxOf(int $amount, int $rate, bool $inclusive): int
    {
        if ($rate <= 0 || $amount <= 0) {
            return 0;
        }

        return $inclusive
            ? (int) round($amount * $rate / (10000 + $rate), 0, \PHP_ROUND_HALF_UP)
            : (int) round($amount * $rate / 10000, 0, \PHP_ROUND_HALF_UP);
    }
}
