<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Controller;

use RomainGenin\AstraCheckoutBundle\Cart\CartContext;
use RomainGenin\AstraCheckoutBundle\Checkout\CheckoutFlow;
use RomainGenin\AstraCheckoutBundle\Repository\OrderRepository;
use RomainGenin\AstraCheckoutBundle\Routing\ShopUrls;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Default checkout pages: each one is just the Checkout block (which handles the forms).
 * Replace them with a single page of yours through astra_checkout.pages.checkout.
 */
#[Route('/checkout')]
final class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartContext $context,
        private readonly CheckoutFlow $flow,
        private readonly OrderRepository $orders,
        private readonly ShopUrls $urls,
    ) {
    }

    #[Route('', name: 'astra_checkout', methods: ['GET'])]
    public function start(): Response
    {
        $cart = $this->context->getCart();
        if (null === $cart || $cart->isEmpty()) {
            return $this->redirect($this->urls->path('cart'));
        }

        return $this->redirect($this->urls->path('checkout', ['step' => $this->flow->firstIncompleteStep($cart)]));
    }

    #[Route('/address', name: 'astra_checkout_address', defaults: ['step' => 'address'], methods: ['GET', 'POST'])]
    #[Route('/shipping', name: 'astra_checkout_shipping', defaults: ['step' => 'shipping'], methods: ['GET', 'POST'])]
    #[Route('/review', name: 'astra_checkout_review', defaults: ['step' => 'review'], methods: ['GET', 'POST'])]
    public function step(string $step): Response
    {
        $response = $this->render('@AstraCheckout/checkout/page.html.twig', ['step' => $step]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    #[Route('/confirmation/{token}', name: 'astra_checkout_confirmation', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function confirmation(string $token): Response
    {
        $order = $this->orders->findOneWithItems(['token' => $token]) ?? throw $this->createNotFoundException();

        $response = $this->render('@AstraCheckout/checkout/confirmation.html.twig', ['order' => $order]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
