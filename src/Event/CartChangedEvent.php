<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Event;

use StudioAstra\AstraCheckoutBundle\Entity\Cart;

/** Dispatched after an item is added, updated or removed (analytics, recommendations…). */
final class CartChangedEvent
{
    public function __construct(public readonly Cart $cart)
    {
    }
}
