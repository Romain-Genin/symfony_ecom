<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Enum;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Shipped, self::Refunded, self::Cancelled],
            self::Processing => [self::Shipped, self::Refunded, self::Cancelled],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return \in_array($status, $this->allowedTransitions(), true);
    }

    /** Cancelling / refunding puts the items back in stock. */
    public function restocks(): bool
    {
        return self::Cancelled === $this || self::Refunded === $this;
    }

    public function isFinal(): bool
    {
        return [] === $this->allowedTransitions();
    }

    public function translationKey(): string
    {
        return 'order.status.' . $this->value;
    }

    /** Position in the customer-facing timeline (null for cancelled/refunded). */
    public function step(): ?int
    {
        return match ($this) {
            self::PendingPayment => 0,
            self::Paid => 1,
            self::Processing => 2,
            self::Shipped => 3,
            self::Delivered => 4,
            default => null,
        };
    }
}
