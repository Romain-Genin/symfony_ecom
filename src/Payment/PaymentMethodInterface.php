<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Payment;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Implement this interface in your application (services are autoconfigured) to plug a payment
 * service provider (Stripe, PayPal, Mollie, Adyen…). See the README for a Stripe Checkout example.
 */
#[AutoconfigureTag('astra_checkout.payment_method')]
interface PaymentMethodInterface
{
    public function getCode(): string;

    /** Translation key or plain text. */
    public function getLabel(): string;

    public function getDescription(): ?string;

    public function supports(Cart $cart): bool;

    /**
     * Called right after the order is placed (status "pending_payment", stock reserved).
     *
     * Return a Response (typically a redirect to the provider's hosted payment page) or null to
     * go straight to the confirmation page. When the provider confirms the payment (webhook / return
     * URL), call OrderStateMachine::apply($order, OrderStatus::Paid).
     */
    public function process(Order $order, Request $request): ?Response;

    /** Text shown on the confirmation page and in the e-mail (bank details…). */
    public function getInstructions(Order $order): ?string;
}
