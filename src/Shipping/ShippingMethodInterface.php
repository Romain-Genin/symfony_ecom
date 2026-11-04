<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Shipping;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Implement this interface in your application (services are autoconfigured) to add a carrier,
 * relay points, weight-based rates…
 */
#[AutoconfigureTag('astra_checkout.shipping_method')]
interface ShippingMethodInterface
{
    public function getCode(): string;

    /** Translation key or plain text. */
    public function getLabel(): string;

    /** e.g. "Livré en 2 à 3 jours ouvrés" — translation key or plain text. */
    public function getDescription(): ?string;

    /** Whether the method can deliver this cart (destination country, weight…). */
    public function supports(Cart $cart): bool;

    /** Cost in minor units. */
    public function calculate(Cart $cart, int $subtotal): int;

    /** Subtotal from which shipping is free (displayed as an incentive), if any. */
    public function getFreeAbove(): ?int;
}
