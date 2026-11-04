<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Event;

use StudioAstra\AstraCheckoutBundle\Entity\Order;
use StudioAstra\AstraCheckoutBundle\Enum\OrderStatus;

final class OrderStatusChangedEvent
{
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
    }
}
