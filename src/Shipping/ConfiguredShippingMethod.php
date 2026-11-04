<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Shipping;

use StudioAstra\AstraCheckoutBundle\Entity\Cart;

/** Flat-rate method defined in astra_checkout.shipping.methods. */
final class ConfiguredShippingMethod implements ShippingMethodInterface
{
    /**
     * @param list<string> $countries empty = every country
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly ?string $description,
        private readonly int $price,
        private readonly ?int $freeAbove,
        private readonly array $countries,
    ) {
    }

    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return $this->label; }
    public function getDescription(): ?string { return $this->description; }
    public function getFreeAbove(): ?int { return $this->freeAbove; }

    public function supports(Cart $cart): bool
    {
        $country = $cart->getShippingAddress()->getCountryCode();

        return [] === $this->countries || null === $country || \in_array($country, $this->countries, true);
    }

    public function calculate(Cart $cart, int $subtotal): int
    {
        return null !== $this->freeAbove && $subtotal >= $this->freeAbove ? 0 : $this->price;
    }
}
