<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Event;

use StudioAstra\AstraCheckoutBundle\Entity\Order;

/** Dispatched once the order is committed in the database (e-mail, ERP export, analytics…). */
final class OrderPlacedEvent
{
    public function __construct(public readonly Order $order)
    {
    }
}
