<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Payment;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bank transfer, cheque, payment on delivery or in store: the order waits for a manual
 * confirmation (bin/console astra:order:status <number> paid).
 */
final class OfflinePaymentMethod implements PaymentMethodInterface
{
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly ?string $description,
        private readonly ?string $instructions,
    ) {
    }

    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return $this->label; }
    public function getDescription(): ?string { return $this->description; }
    public function supports(Cart $cart): bool { return true; }
    public function process(Order $order, Request $request): ?Response { return null; }

    public function getInstructions(Order $order): ?string
    {
        return null === $this->instructions ? null : strtr($this->instructions, [
            '{number}' => $order->getNumber(),
            '{total}' => number_format($order->getTotal() / 100, 2, ',', ' ') . ' ' . $order->getCurrency(),
        ]);
    }
}
