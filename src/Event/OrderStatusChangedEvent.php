<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Event;

use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;

final class OrderStatusChangedEvent
{
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
    }
}
