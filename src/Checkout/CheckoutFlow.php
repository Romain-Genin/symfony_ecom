<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Checkout;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodRegistry;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;

/**
 * Checkout steps: address → shipping & payment → review. A step is reachable only when the
 * previous ones are complete, so deep links and the back button always land somewhere valid.
 */
final class CheckoutFlow
{
    public const STEPS = ['address', 'shipping', 'review'];

    public function __construct(
        private readonly ShippingMethodRegistry $shipping,
        private readonly PaymentMethodRegistry $payment,
    ) {
    }

    /** First incomplete step, "cart" when there is nothing to order. */
    public function firstIncompleteStep(Cart $cart): string
    {
        if ($cart->isEmpty()) {
            return 'cart';
        }
        if (!$this->isAddressComplete($cart)) {
            return 'address';
        }
        if (!$this->isShippingComplete($cart)) {
            return 'shipping';
        }

        return 'review';
    }

    public function canAccess(Cart $cart, string $step): bool
    {
        $first = $this->firstIncompleteStep($cart);
        if ('cart' === $first) {
            return false;
        }

        return array_search($step, self::STEPS, true) <= array_search($first, self::STEPS, true);
    }

    public function isAddressComplete(Cart $cart): bool
    {
        return null !== $cart->getEmail()
            && $cart->getShippingAddress()->isComplete()
            && ($cart->isBillingSameAsShipping() || $cart->getBillingAddress()->isComplete());
    }

    public function isShippingComplete(Cart $cart): bool
    {
        $shipping = null === $cart->getShippingMethod() ? null : $this->shipping->get($cart->getShippingMethod());
        $payment = null === $cart->getPaymentMethod() ? null : $this->payment->get($cart->getPaymentMethod());

        return null !== $shipping && $shipping->supports($cart) && null !== $payment && $payment->supports($cart);
    }
}
