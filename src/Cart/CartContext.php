<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Cart;

use Doctrine\ORM\EntityManagerInterface;
use StudioAstra\AstraCheckoutBundle\Entity\Cart;
use StudioAstra\AstraCheckoutBundle\Customer\CustomerInfo;
use StudioAstra\AstraCheckoutBundle\Repository\CartRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resolves the current visitor's cart.
 *
 * Reading never creates anything and never starts a session: catalog pages stay cacheable by a
 * reverse proxy, and bots never fill the cart table. The cart is created on the first "add to cart".
 */
final class CartContext implements ResetInterface
{
    public const SESSION_KEY = '_astra_checkout_cart';

    private ?Cart $cart = null;
    private bool $resolved = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CartRepository $carts,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly CustomerInfo $customers,
    ) {
    }

    public function getCart(): ?Cart
    {
        if ($this->resolved) {
            return $this->cart;
        }
        $this->resolved = true;

        if (null !== ($customer = $this->getCustomer())) {
            return $this->cart = $this->carts->findForCustomer($customer);
        }

        $id = $this->getSessionCartId();
        if (null !== $id) {
            $cart = $this->carts->findWithItems($id);
            $this->cart = null !== $cart && null === $cart->getCustomer() ? $cart : null;
        }

        return $this->cart;
    }

    public function getOrCreateCart(): Cart
    {
        if (null !== ($cart = $this->getCart())) {
            return $cart;
        }

        $cart = new Cart();
        $customer = $this->getCustomer();
        $cart->setCustomer($customer);
        $cart->setEmail(null === $customer ? null : $this->customers->email($customer));
        $this->em->persist($cart);
        $this->em->flush();

        if (null === $customer) {
            $this->requestStack->getSession()->set(self::SESSION_KEY, $cart->getId());
        }

        return $this->cart = $cart;
    }

    /**
     * Called on login: the anonymous cart is merged into the customer's cart (quantities added,
     * capped by the stock), or simply attached to the customer when they had none.
     */
    public function mergeAnonymousCartInto(object $customer, CartManager $manager): void
    {
        $id = $this->getSessionCartId();
        if (null === $id) {
            return;
        }
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
        $anonymous = $this->carts->findWithItems($id);
        if (null === $anonymous || null !== $anonymous->getCustomer()) {
            return;
        }

        $existing = $this->carts->findForCustomer($customer);
        if (null === $existing) {
            $anonymous->setCustomer($customer);
            $anonymous->setEmail($this->customers->email($customer));
            $this->em->flush();
            $this->cart = $anonymous;
        } else {
            $manager->merge($anonymous, $existing);
            $this->em->remove($anonymous);
            $this->em->flush();
            $this->cart = $existing;
        }
        $this->resolved = true;
    }

    /** Long-running runtimes (FrankenPHP worker, RoadRunner, tests): no state leaks between requests. */
    public function reset(): void
    {
        $this->cart = null;
        $this->resolved = false;
    }

    /** Forgets the cart (after the order is placed). */
    public function forget(): void
    {
        $this->cart = null;
        $this->resolved = false;
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request && $request->hasSession()) {
            $request->getSession()->remove(self::SESSION_KEY);
        }
    }

    /** The logged-in user when it is a shop customer (astra_checkout.customer.class). */
    public function getCustomer(): ?object
    {
        $user = $this->security->getUser();

        return $this->customers->supports($user) ? $user : null;
    }

    private function getSessionCartId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession() || (!$request->hasPreviousSession() && !$request->getSession()->isStarted())) {
            return null;
        }
        $id = $request->getSession()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }
}
