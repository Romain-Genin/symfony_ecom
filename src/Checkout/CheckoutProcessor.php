<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Checkout;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use StudioAstra\AstraCheckoutBundle\Cart\CartManager;
use StudioAstra\AstraCheckoutBundle\Entity\Cart;
use StudioAstra\AstraCheckoutBundle\Entity\Order;
use StudioAstra\AstraCheckoutBundle\Entity\OrderItem;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use StudioAstra\AstraCheckoutBundle\Entity\Promotion;
use StudioAstra\AstraCheckoutBundle\Promotion\PromotionCalculator;
use StudioAstra\AstraCheckoutBundle\Event\OrderPlacedEvent;
use StudioAstra\AstraCheckoutBundle\Exception\CheckoutException;
use StudioAstra\AstraCheckoutBundle\Order\OrderNumberGenerator;
use StudioAstra\AstraCheckoutBundle\Payment\PaymentMethodRegistry;
use StudioAstra\AstraCheckoutBundle\Pricing\TotalsCalculator;
use StudioAstra\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns a complete cart into an order, atomically: variants are locked (SELECT … FOR UPDATE),
 * stock is checked and decremented, the order snapshot is written and the cart deleted in one
 * transaction. Two customers can never buy the last item at the same time.
 */
final class CheckoutProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CheckoutFlow $flow,
        private readonly CartManager $cartManager,
        private readonly TotalsCalculator $totals,
        private readonly ShippingMethodRegistry $shipping,
        private readonly PaymentMethodRegistry $payment,
        private readonly OrderNumberGenerator $numbers,
        private readonly ShopSettings $settings,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @throws CheckoutException
     */
    public function placeOrder(Cart $cart, string $locale = 'fr'): Order
    {
        if ([] !== $this->cartManager->revalidate($cart)) {
            throw new CheckoutException('checkout.error.cart_changed');
        }
        if ('review' !== $this->flow->firstIncompleteStep($cart)) {
            throw new CheckoutException('checkout.error.incomplete');
        }

        $shipping = $this->shipping->get((string) $cart->getShippingMethod());
        $payment = $this->payment->get((string) $cart->getPaymentMethod());

        $order = $this->em->wrapInTransaction(function () use ($cart, $shipping, $payment, $locale): Order {
            foreach ($cart->getItems() as $item) {
                $variant = $item->getVariant();
                $this->em->refresh($variant, LockMode::PESSIMISTIC_WRITE);
                if (!$variant->isAvailable($item->getQuantity())) {
                    throw new CheckoutException('cart.error.not_enough_stock', ['%product%' => $variant->getFullName(), '%stock%' => max(0, (int) $variant->getStock())]);
                }
            }

            $totals = $this->totals->calculate($cart);

            // Usage limits are checked again under lock: a limited code cannot be used twice concurrently.
            foreach ($totals->promotions->discounts as $discount) {
                $promotion = $this->em->find(Promotion::class, $discount->promotionId, LockMode::PESSIMISTIC_WRITE);
                if (null === $promotion || $promotion->isUsedUp()) {
                    throw new CheckoutException('coupon.error.usage_limit');
                }
                $promotion->incrementUsage();
            }
            $order = new Order($this->numbers->generate(), (string) $cart->getEmail(), $this->settings->getCurrency());
            $order->setCustomer($cart->getCustomer());
            $order->setLocale($locale);
            $order->setShippingAddress($cart->getShippingAddress());
            $order->setBillingAddress($cart->getEffectiveBillingAddress());
            $order->setShippingMethod($shipping->getCode(), $this->trans($shipping->getLabel(), $locale));
            $order->setPaymentMethod($payment->getCode(), $this->trans($payment->getLabel(), $locale));
            $order->setCustomerNote($cart->getCustomerNote());
            $order->setTotals($totals->toArray(), $totals->pricesIncludeTax);
            $order->setPromotions(array_map(static fn ($d): array => $d->toArray(), $totals->promotions->discounts));
            $order->setCouponCode(null !== $cart->getCouponCode() && $totals->promotions->hasCode($cart->getCouponCode()) ? $cart->getCouponCode() : null);

            foreach ($cart->getItems() as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->getVariant();
                $variant->decreaseStock($item->getQuantity());
                $key = PromotionCalculator::key($item);
                $order->addItem(new OrderItem($order, $variant, $item->getQuantity(), $totals->itemTaxes[$key] ?? 0, $totals->promotions->itemDiscounts[$key] ?? 0));
            }

            $this->em->persist($order);
            $this->em->remove($cart);
            $this->em->flush();

            return $order;
        });

        $this->dispatcher->dispatch(new OrderPlacedEvent($order));

        return $order;
    }

    private function trans(string $label, string $locale): string
    {
        return $this->translator?->trans($label, [], 'AstraCheckout', $locale) ?? $label;
    }
}
