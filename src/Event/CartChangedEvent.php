<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Event;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;

/** Dispatched after an item is added, updated or removed (analytics, recommendations…). */
final class CartChangedEvent
{
    public function __construct(public readonly Cart $cart)
    {
    }
}
