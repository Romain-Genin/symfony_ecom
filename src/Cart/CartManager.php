<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Cart;

use Doctrine\ORM\EntityManagerInterface;
use StudioAstra\AstraCheckoutBundle\Entity\Cart;
use StudioAstra\AstraCheckoutBundle\Entity\CartItem;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use StudioAstra\AstraCheckoutBundle\Event\CartChangedEvent;
use StudioAstra\AstraCheckoutBundle\Exception\CartException;
use StudioAstra\AstraCheckoutBundle\Promotion\CouponValidator;
use StudioAstra\AstraCheckoutBundle\Promotion\PromotionProvider;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CartManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShopSettings $settings,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?CouponValidator $coupons = null,
        private readonly ?PromotionProvider $promotions = null,
    ) {
    }

    /**
     * @throws CartException with the precise reason (expired, minimum not reached…)
     */
    public function applyCoupon(Cart $cart, string $code): void
    {
        $code = strtoupper(trim($code));
        if ('' === $code || null === $this->coupons || null === $this->promotions) {
            throw new CartException('coupon.error.unknown');
        }
        $this->coupons->validate($this->promotions->findCoupon($code), $cart);
        $cart->setCouponCode($code);
        $this->save($cart);
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->setCouponCode(null);
        $this->save($cart);
    }

    /**
     * @throws CartException when the variant cannot be sold in this quantity
     */
    public function add(Cart $cart, ProductVariant $variant, int $quantity = 1): CartItem
    {
        if ($quantity < 1) {
            throw new CartException('cart.error.invalid_quantity');
        }
        if (!$variant->getProduct()->isEnabled()) {
            throw new CartException('cart.error.unavailable', ['%product%' => $variant->getFullName()]);
        }

        $item = $cart->findItem($variant);
        $wanted = ($item?->getQuantity() ?? 0) + $quantity;
        $this->assertSellable($variant, $wanted);

        if (null === $item) {
            $item = new CartItem($cart, $variant, $wanted);
            $cart->addItem($item);
            $this->em->persist($item);
        } else {
            $item->setQuantity($wanted);
        }

        $this->save($cart);

        return $item;
    }

    public function setQuantity(Cart $cart, CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($cart, $item);

            return;
        }
        $this->assertSellable($item->getVariant(), $quantity);
        $item->setQuantity($quantity);
        $this->save($cart);
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        $cart->removeItem($item);
        $this->save($cart);
    }

    public function clear(Cart $cart): void
    {
        $cart->clearItems();
        $this->save($cart);
    }

    /**
     * Fixes the cart against the current catalog (disabled products, stock sold meanwhile) and
     * returns what changed, so the visitor is told instead of failing at the last step.
     *
     * @return list<array{key: string, parameters: array<string, string|int>}>
     */
    public function revalidate(Cart $cart): array
    {
        $notices = [];
        foreach ($cart->getItems()->toArray() as $item) {
            $variant = $item->getVariant();
            $name = $variant->getFullName();
            if (!$variant->getProduct()->isEnabled() || (null !== $variant->getStock() && $variant->getStock() <= 0)) {
                $cart->removeItem($item);
                $notices[] = ['key' => 'cart.notice.removed', 'parameters' => ['%product%' => $name]];
                continue;
            }
            $max = min($this->settings->getMaxQuantityPerItem(), $variant->getStock() ?? \PHP_INT_MAX);
            if ($item->getQuantity() > $max) {
                $item->setQuantity($max);
                $notices[] = ['key' => 'cart.notice.reduced', 'parameters' => ['%product%' => $name, '%quantity%' => $max]];
            }
        }
        if (null !== ($code = $cart->getCouponCode()) && null !== $this->coupons && null !== $this->promotions) {
            try {
                $this->coupons->validate($cart->isEmpty() ? null : $this->promotions->findCoupon($code), $cart);
            } catch (CartException $e) {
                $cart->setCouponCode(null);
                if (!$cart->isEmpty()) {
                    $notices[] = ['key' => 'coupon.notice.removed', 'parameters' => ['%code%' => $code]];
                    $notices[] = ['key' => $e->getMessage(), 'parameters' => $e->getParameters()];
                }
                $this->em->flush();
            }
        }
        if ($notices) {
            $this->save($cart);
        }

        return $notices;
    }

    /** Moves the items of $from into $to (used when an anonymous visitor logs in). */
    public function merge(Cart $from, Cart $to): void
    {
        foreach ($from->getItems() as $item) {
            $variant = $item->getVariant();
            $existing = $to->findItem($variant);
            $quantity = min(
                ($existing?->getQuantity() ?? 0) + $item->getQuantity(),
                $this->settings->getMaxQuantityPerItem(),
                $variant->getStock() ?? \PHP_INT_MAX,
            );
            if ($quantity <= 0) {
                continue;
            }
            if (null === $existing) {
                $new = new CartItem($to, $variant, $quantity);
                $to->addItem($new);
                $this->em->persist($new);
            } else {
                $existing->setQuantity($quantity);
            }
        }
        $this->dispatcher->dispatch(new CartChangedEvent($to));
    }

    private function assertSellable(ProductVariant $variant, int $quantity): void
    {
        $max = $this->settings->getMaxQuantityPerItem();
        if ($quantity > $max) {
            throw new CartException('cart.error.max_quantity', ['%max%' => $max]);
        }
        if (!$variant->isAvailable($quantity)) {
            if (($variant->getStock() ?? 0) <= 0) {
                throw new CartException('cart.error.out_of_stock', ['%product%' => $variant->getFullName()]);
            }
            throw new CartException('cart.error.not_enough_stock', ['%product%' => $variant->getFullName(), '%stock%' => (int) $variant->getStock()]);
        }
    }

    private function save(Cart $cart): void
    {
        $cart->touch();
        $this->em->flush();
        $this->dispatcher->dispatch(new CartChangedEvent($cart));
    }
}
