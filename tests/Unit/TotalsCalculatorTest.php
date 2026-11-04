<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StudioAstra\AstraCheckoutBundle\Entity\Cart;
use StudioAstra\AstraCheckoutBundle\Entity\CartItem;
use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use StudioAstra\AstraCheckoutBundle\Pricing\TotalsCalculator;
use StudioAstra\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;
use StudioAstra\AstraCheckoutBundle\ShopSettings;

final class TotalsCalculatorTest extends TestCase
{
    private function calculator(bool $inclusive): TotalsCalculator
    {
        $settings = new ShopSettings(['prices_include_tax' => $inclusive, 'shipping' => ['tax_rate' => 2000]]);
        $registry = new ShippingMethodRegistry([
            'standard' => ['label' => 'Standard', 'description' => null, 'price' => 500, 'free_above' => 5000, 'countries' => []],
        ]);

        return new TotalsCalculator($settings, $registry);
    }

    private function cart(int $price, int $quantity, int $rate = 2000): Cart
    {
        $product = (new Product('P', 'p'))->setTaxRate($rate);
        $variant = new ProductVariant('SKU', $price);
        $product->addVariant($variant);
        $cart = new Cart();
        $cart->addItem(new CartItem($cart, $variant, $quantity));

        return $cart;
    }

    public function testTaxInclusivePrices(): void
    {
        $totals = $this->calculator(true)->calculate($this->cart(1200, 2), 'standard');
        self::assertSame(2400, $totals->subtotal);
        self::assertSame(500, $totals->shipping);
        self::assertSame(2900, $totals->total);
        self::assertSame(400 + 83, $totals->tax); // 2400 × 20/120 + 500 × 20/120
        self::assertSame([2000 => 483], $totals->taxByRate);
    }

    public function testTaxExclusivePricesAddTaxToTheTotal(): void
    {
        $totals = $this->calculator(false)->calculate($this->cart(1000, 1, 550), 'standard');
        self::assertSame(1000 + 500 + 55 + 100, $totals->total);
    }

    public function testFreeShippingThreshold(): void
    {
        $totals = $this->calculator(true)->calculate($this->cart(2500, 2), 'standard');
        self::assertSame(0, $totals->shipping);
    }

    public function testUnknownShippingMethodLeavesShippingUndefined(): void
    {
        self::assertNull($this->calculator(true)->calculate($this->cart(100, 1), '')->shipping);
    }

    public function testDenormalizedListingFields(): void
    {
        $product = new Product('P', 'p');
        $product->addVariant((new ProductVariant('A', 3000, 0))->setCompareAtPrice(4000));
        $product->addVariant(new ProductVariant('B', 2000, 5));
        self::assertSame(2000, $product->getMinPrice());
        self::assertSame(3000, $product->getMaxPrice());
        self::assertTrue($product->isInStock());
        self::assertTrue($product->hasPriceRange());
        self::assertSame('B', $product->getDefaultVariant()->getSku());

        $product->getVariants()->last()->decreaseStock(5);
        self::assertFalse($product->isInStock());
    }
}
