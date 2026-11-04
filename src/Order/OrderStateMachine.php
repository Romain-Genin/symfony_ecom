<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Order;

use Doctrine\ORM\EntityManagerInterface;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Entity\Promotion;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;
use RomainGenin\AstraCheckoutBundle\Event\OrderStatusChangedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The only way to change an order status: checks the transition, puts the stock back on
 * cancellation / refund, and dispatches OrderStatusChangedEvent (e-mails, ERP…).
 */
final class OrderStateMachine
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function can(Order $order, OrderStatus $to): bool
    {
        return $order->getStatus()->canTransitionTo($to);
    }

    public function apply(Order $order, OrderStatus $to): void
    {
        $from = $order->getStatus();
        if ($from === $to) {
            return;
        }
        if (!$from->canTransitionTo($to)) {
            throw new \LogicException(sprintf('Order %s cannot go from "%s" to "%s".', $order->getNumber(), $from->value, $to->value));
        }

        $this->em->wrapInTransaction(function () use ($order, $from, $to): void {
            if ($to->restocks() && !$from->restocks() && OrderStatus::Delivered !== $from && OrderStatus::Shipped !== $from) {
                foreach ($order->getItems() as $item) {
                    $item->getVariant()?->increaseStock($item->getQuantity());
                }
            }
            // A cancelled order gives the promotion use back (single-use codes become usable again).
            if (OrderStatus::Cancelled === $to) {
                foreach ($order->getPromotions() as $snapshot) {
                    $this->em->find(Promotion::class, $snapshot['id'])?->decrementUsage();
                }
            }
            $order->setStatus($to);
            $this->em->flush();
        });

        $this->dispatcher->dispatch(new OrderStatusChangedEvent($order, $from, $to));
    }
}
