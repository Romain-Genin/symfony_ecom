<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads the shop routes (controller attributes), then applies astra_checkout.routing:
 * configurable paths ("/produits", "/panier"…), optional home page, and removes the account
 * pages your application provides itself (login, registration…) when reusing your users.
 */
final class ShopRouteLoader extends Loader
{
    /** Default path prefix => key of astra_checkout.routing.paths */
    private const SECTIONS = [
        '/products' => 'catalog',
        '/search' => 'search',
        '/c/' => 'category',
        '/p/' => 'product',
        '/cart' => 'cart',
        '/checkout' => 'checkout',
        '/account' => 'account',
    ];

    /** Routes only registered with the built-in accounts. */
    private const AUTH_ROUTES = [
        'astra_login', 'astra_logout', 'astra_register',
        'astra_password_reset_request', 'astra_password_reset', 'astra_password_reset_form',
        'astra_account_profile', 'astra_account_password', 'astra_account_delete',
    ];

    private bool $loaded = false;

    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        private readonly bool $home,
        private readonly array $paths,
        private readonly bool $auth,
        /** @var list<string> shop pages replaced by yours (astra_checkout.pages) */
        private readonly array $replaced = [],
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('The "astra_checkout" routes are already loaded.');
        }
        $this->loaded = true;

        /** @var RouteCollection $routes */
        $routes = $this->import(\dirname(__DIR__) . '/Controller/', 'attribute');

        foreach ($routes->all() as $name => $route) {
            if (('astra_home' === $name && !$this->home) || (!$this->auth && \in_array($name, self::AUTH_ROUTES, true)) || \in_array($name, $this->replaced, true)) {
                $routes->remove($name);
                continue;
            }
            $path = $route->getPath();
            foreach (self::SECTIONS as $prefix => $key) {
                $base = rtrim($prefix, '/');
                if ($path === $base || str_starts_with($path, $base . '/')) {
                    $configured = '/' . trim($this->paths[$key] ?? $base, '/');
                    $route->setPath($configured . substr($path, \strlen($base)));
                    break;
                }
            }
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'astra_checkout' === $type;
    }
}
