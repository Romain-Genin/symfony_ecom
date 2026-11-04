<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Routing;

use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Every link of the shop goes through here, so it can point to YOUR pages:
 * astra_checkout.pages.product: app_product → product links become path('app_product', {slug}).
 *
 * Parameters given to your routes: category/product {slug} (+ ?variant), search ?q,
 * checkout ?step, confirmation {token}, order {number}, address_edit {id}.
 */
final class ShopUrls
{
    public const PAGES = [
        'home' => 'astra_home',
        'catalog' => 'astra_catalog',
        'category' => 'astra_category',
        'product' => 'astra_product',
        'search' => 'astra_search',
        'cart' => 'astra_cart',
        'checkout' => 'astra_checkout',
        'confirmation' => 'astra_checkout_confirmation',
        'account' => 'astra_account',
        'orders' => 'astra_account_orders',
        'order' => 'astra_account_order',
        'addresses' => 'astra_account_addresses',
        'address_new' => 'astra_account_address_new',
        'address_edit' => 'astra_account_address_edit',
    ];

    /**
     * @param array<string, ?string> $pages page => your route name (null = the shop page)
     */
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly ShopSettings $settings,
        private readonly array $pages = [],
    ) {
    }

    public function isCustom(string $page): bool
    {
        return null !== ($this->pages[$page] ?? null);
    }

    public function route(string $page): string
    {
        return match ($page) {
            'login' => $this->settings->getLoginRoute(),
            'logout' => $this->settings->getLogoutRoute(),
            'register' => (string) $this->settings->getRegisterRoute(),
            default => $this->pages[$page] ?? self::PAGES[$page] ?? throw new \InvalidArgumentException(sprintf('Unknown shop page "%s".', $page)),
        };
    }

    /** @param array<string, mixed> $parameters */
    public function path(string $page, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        if ('home' === $page && !$this->isCustom('home') && !$this->settings->isHomeEnabled()) {
            return $this->router->getContext()->getBaseUrl() . '/';
        }
        // The built-in checkout has one URL per step; your checkout page receives ?step=.
        if ('checkout' === $page && !$this->isCustom('checkout') && isset($parameters['step'])) {
            $step = $parameters['step'];
            unset($parameters['step']);

            return $this->router->generate('astra_checkout_' . $step, $parameters, $referenceType);
        }

        return $this->router->generate($this->route($page), $parameters, $referenceType);
    }

    /** @param array<string, mixed> $parameters */
    public function url(string $page, array $parameters = []): string
    {
        return $this->path($page, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** Shop routes replaced by your pages (not registered by ShopRouteLoader). */
    public static function replacedRoutes(array $pages): array
    {
        $routes = [];
        foreach ($pages as $page => $route) {
            if (null !== $route && isset(self::PAGES[$page])) {
                $routes[] = self::PAGES[$page];
                if ('checkout' === $page) {
                    array_push($routes, 'astra_checkout_address', 'astra_checkout_shipping', 'astra_checkout_review');
                }
            }
        }

        return $routes;
    }
}
