<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Controller;

use StudioAstra\AstraCheckoutBundle\Cart\CartContext;
use StudioAstra\AstraCheckoutBundle\Cart\CartManager;
use StudioAstra\AstraCheckoutBundle\Entity\Cart;
use StudioAstra\AstraCheckoutBundle\Exception\CartException;
use StudioAstra\AstraCheckoutBundle\Http\SameOriginGuard;
use StudioAstra\AstraCheckoutBundle\Pricing\MoneyFormatter;
use StudioAstra\AstraCheckoutBundle\Pricing\TotalsCalculator;
use StudioAstra\AstraCheckoutBundle\Repository\ProductVariantRepository;
use StudioAstra\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Every action works as a plain HTML form POST (no JavaScript needed) and answers JSON when the
 * Alpine components ask for it (Accept: application/json).
 */
final class CartController extends AbstractController
{
    public function __construct(
        private readonly CartContext $context,
        private readonly CartManager $manager,
        private readonly TotalsCalculator $totals,
        private readonly ProductVariantRepository $variants,
        private readonly ShippingMethodRegistry $shipping,
        private readonly MoneyFormatter $money,
        private readonly SameOriginGuard $origin,
        private readonly TranslatorInterface $translator,
        private readonly Packages $packages,
        private readonly \StudioAstra\AstraCheckoutBundle\Presentation\CartPresenter $presenter,
        private readonly \StudioAstra\AstraCheckoutBundle\Routing\ShopUrls $urls,
        private readonly \StudioAstra\AstraCheckoutBundle\Customer\CustomerInfo $customers,
    ) {
    }

    #[Route('/cart', name: 'astra_cart', methods: ['GET'])]
    public function show(): Response
    {
        $response = $this->render('@AstraCheckout/cart/show.html.twig');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /** Personal data of the header (badge, mini-cart, first name), loaded once per page by Alpine. */
    #[Route('/cart/summary', name: 'astra_cart_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        return $this->json($this->summaryData($this->context->getCart()), 200, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/cart/add', name: 'astra_cart_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $this->origin->assert($request);
        $variant = $this->variants->findSellable($request->request->getInt('variant'));
        if (null === $variant) {
            return $this->fail($request, new CartException('cart.error.unavailable', ['%product%' => '']));
        }

        try {
            $cart = $this->context->getOrCreateCart();
            $this->manager->add($cart, $variant, max(1, $request->request->getInt('quantity', 1)));
        } catch (CartException $e) {
            return $this->fail($request, $e);
        }

        $message = $this->translator->trans('cart.added', ['%product%' => $variant->getFullName()], 'AstraCheckout');

        return $this->succeed($request, $cart, $message);
    }

    #[Route('/cart/items/{id}', name: 'astra_cart_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $this->origin->assert($request);
        $cart = $this->context->getCart();
        $item = $cart?->findItemById($id);
        if (null === $cart || null === $item) {
            return $this->fail($request, new CartException('cart.error.item_not_found'), 404);
        }

        $name = $item->getVariant()->getFullName();
        try {
            $quantity = $request->request->getInt('quantity');
            $this->manager->setQuantity($cart, $item, $quantity);
        } catch (CartException $e) {
            return $this->fail($request, $e);
        }

        $message = $this->translator->trans($quantity > 0 ? 'cart.updated' : 'cart.removed', ['%product%' => $name, '%quantity%' => $quantity], 'AstraCheckout');

        return $this->succeed($request, $cart, $message);
    }

    #[Route('/cart/items/{id}/remove', name: 'astra_cart_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, int $id): Response
    {
        $this->origin->assert($request);
        $cart = $this->context->getCart();
        $item = $cart?->findItemById($id);
        if (null === $cart || null === $item) {
            return $this->fail($request, new CartException('cart.error.item_not_found'), 404);
        }
        $name = $item->getVariant()->getFullName();
        $this->manager->remove($cart, $item);

        return $this->succeed($request, $cart, $this->translator->trans('cart.removed', ['%product%' => $name], 'AstraCheckout'));
    }

    #[Route('/cart/coupon', name: 'astra_cart_coupon', methods: ['POST'])]
    public function applyCoupon(Request $request): Response
    {
        $this->origin->assert($request);
        $cart = $this->context->getCart();
        if (null === $cart || $cart->isEmpty()) {
            return $this->fail($request, new CartException('coupon.error.empty_cart'));
        }
        $code = strtoupper(trim((string) $request->request->get('code')));
        try {
            $this->manager->applyCoupon($cart, $code);
        } catch (CartException $e) {
            if ($this->wantsJson($request)) {
                return $this->fail($request, $e);
            }
            // Shown next to the field (not as a page-level flash), with the typed code kept.
            $request->getSession()->set('_astra_coupon_attempt', $code);
            $request->getSession()->set('_astra_coupon_error', $this->translator->trans($e->getMessage(), $e->getParameters(), 'AstraCheckout'));

            return $this->redirect($this->urls->path('cart'), Response::HTTP_SEE_OTHER);
        }

        $applied = $this->totals->calculate($cart, '')->promotions;
        $key = $applied->hasCode($code) ? 'coupon.applied' : 'coupon.applied_not_best';

        return $this->succeed($request, $cart, $this->translator->trans($key, ['%code%' => $code], 'AstraCheckout'));
    }

    #[Route('/cart/coupon/remove', name: 'astra_cart_coupon_remove', methods: ['POST'])]
    public function removeCoupon(Request $request): Response
    {
        $this->origin->assert($request);
        $cart = $this->context->getCart();
        if (null !== $cart && null !== $cart->getCouponCode()) {
            $this->manager->removeCoupon($cart);
        }

        return null === $cart
            ? $this->redirect($this->urls->path('cart'), Response::HTTP_SEE_OTHER)
            : $this->succeed($request, $cart, $this->translator->trans('coupon.removed', [], 'AstraCheckout'));
    }

    private function succeed(Request $request, Cart $cart, string $message): Response
    {
        if ($this->wantsJson($request)) {
            return $this->json(['ok' => true, 'message' => $message, 'cart' => $this->summaryData($cart)], 200, ['Cache-Control' => 'private, no-store']);
        }
        $this->addFlash('success', $message);

        return $this->redirect($this->urls->path('cart'), Response::HTTP_SEE_OTHER);
    }

    private function fail(Request $request, CartException $e, int $status = 422): Response
    {
        $message = $this->translator->trans($e->getMessage(), $e->getParameters(), 'AstraCheckout');
        if ($this->wantsJson($request)) {
            return $this->json(['ok' => false, 'message' => $message, 'cart' => $this->summaryData($this->context->getCart())], $status, ['Cache-Control' => 'private, no-store']);
        }
        $this->addFlash('error', $message);

        return $this->redirect($this->safeReferer($request), Response::HTTP_SEE_OTHER);
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains((string) $request->headers->get('Accept'), 'application/json');
    }

    private function safeReferer(Request $request): string
    {
        $referer = (string) $request->headers->get('Referer');
        $host = parse_url($referer, \PHP_URL_HOST);

        return null !== $host && $host === $request->getHost() ? $referer : $this->urls->path('cart');
    }

    /** @return array<string, mixed> */
    private function summaryData(?Cart $cart): array
    {
        $customer = $this->context->getCustomer();
        $totals = null !== $cart ? $this->totals->calculate($cart, '') : null;
        $items = [];
        foreach ($cart?->getItems() ?? [] as $item) {
            $product = $item->getProduct();
            $image = $product->getMainImage();
            $items[] = [
                'id' => $item->getId(),
                'name' => $product->getName(),
                'variant' => $item->getVariant()->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $this->money->format($item->getUnitPrice()),
                'total' => $this->money->format($item->getTotal()),
                'url' => $this->urls->path('product', ['slug' => $product->getSlug(), 'variant' => $item->getVariant()->getId()]),
                'updateUrl' => $this->generateUrl('astra_cart_update', ['id' => $item->getId()]),
                'removeUrl' => $this->generateUrl('astra_cart_remove', ['id' => $item->getId()]),
                'image' => null === $image ? null : [
                    'src' => $image->isExternal() ? $image->getPath() : $this->packages->getUrl($image->getPath()),
                    'alt' => '',
                ],
            ];
        }

        return [
            'count' => $totals?->itemCount ?? 0,
            'subtotal' => $this->money->format($totals?->getNetSubtotal() ?? 0),
            'discounts' => array_map(fn ($d): array => ['label' => $d->label, 'code' => $d->code, 'amount' => $d->freeShipping ? null : $this->money->format($d->amount)], $totals?->promotions->discounts ?? []),
            'items' => $items,
            'freeShipping' => $this->presenter->freeShipping($cart),
            'customer' => null === $customer ? null : ['firstName' => $this->customers->displayName($customer)],
        ];
    }
}
