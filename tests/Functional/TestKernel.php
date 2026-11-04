<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use StudioAstra\AstraCheckoutBundle\AstraCheckoutBundle;
use StudioAstra\AstraCheckoutBundle\Customer\Customer;
use StudioAstra\AstraCheckoutBundle\Tests\Functional\App\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal application using the bundle the way the README describes it.
 * Also used by tests/app/public/index.php to browse the shop locally.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(string $environment = 'test', bool $debug = true)
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new TwigComponentBundle(), new SecurityBundle(), new DoctrineBundle(), new AstraCheckoutBundle()];
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__) . '/app';
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__) . '/var/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__) . '/var/' . $this->environment . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $test = 'dev' !== $this->environment;
        // "host": an existing website (own User entity, login page and home page) adding the shop.
        $host = 'host' === $this->environment;
        $container->extension('framework', [
            'test' => $test,
            'secret' => 'test',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'session' => $test ? ['storage_factory_id' => 'session.storage.factory.mock_file'] : ['handler_id' => null, 'cookie_secure' => 'auto', 'cookie_samesite' => 'lax'],
            'csrf_protection' => true,
            'default_locale' => 'fr',
            'enabled_locales' => ['fr', 'en'],
            'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['fr']],
            'router' => ['utf8' => true],
            'php_errors' => ['log' => true],
            'mailer' => ['dsn' => 'null://null'],
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => ['App\\Twig\\Components\\' => 'components/']]);
        // TwigBundle only wires TemplatedEmail rendering when symfony/mailer is a non-dev dependency.
        $container->import(\dirname(__DIR__, 2) . '/vendor/symfony/twig-bundle/Resources/config/mailer.php');
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///' . \dirname(__DIR__) . '/var/' . $this->environment . '/shop.sqlite'],
            'orm' => array_filter([
                'auto_mapping' => true,
                'mappings' => $host ? ['App' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__ . '/App', 'prefix' => 'StudioAstra\\AstraCheckoutBundle\\Tests\\Functional\\App']] : null,
                'enable_lazy_ghost_objects' => \PHP_VERSION_ID < 80400 ? true : null,
                'report_fields_where_declared' => true,
                'controller_resolver' => ['auto_mapping' => false],
            ], static fn ($v) => null !== $v),
        ]);
        if ($host) {
            $container->extension('security', [
                'password_hashers' => [User::class => ['algorithm' => 'plaintext']],
                'providers' => ['app_users' => ['entity' => ['class' => User::class, 'property' => 'email']]],
                'firewalls' => ['main' => [
                    'lazy' => true,
                    'provider' => 'app_users',
                    'form_login' => ['login_path' => 'app_login', 'check_path' => 'app_login', 'enable_csrf' => false],
                    'logout' => ['path' => 'app_logout', 'target' => 'app_home'],
                ]],
            ]);
            $container->extension('astra_checkout', [
                'customer' => ['class' => User::class, 'properties' => ['first_name' => 'givenName', 'last_name' => 'familyName']],
                'routing' => ['home' => false, 'paths' => [
                    'catalog' => '/produits', 'search' => '/recherche', 'category' => '/categorie', 'product' => '/produit',
                    'cart' => '/panier', 'checkout' => '/commande', 'account' => '/mon-compte',
                ]],
                'account' => ['register_route' => 'app_register'],
                // The site's own pages, built with the shop blocks.
                'pages' => [
                    'catalog' => 'app_catalog', 'category' => 'app_category', 'product' => 'app_product', 'search' => 'app_search',
                    'cart' => 'app_cart', 'checkout' => 'app_checkout', 'confirmation' => 'app_confirmation',
                    'account' => 'app_orders', 'orders' => 'app_orders', 'order' => 'app_order',
                    'addresses' => 'app_addresses', 'address_new' => 'app_address_new', 'address_edit' => 'app_address_edit',
                ],
                // The site's own layout and Tailwind build; the site loads Alpine itself.
                'layout' => 'site_base.html.twig',
                'assets' => ['css' => false, 'js' => 'components'],
            ]);
            $container->services()->set('logger', \Psr\Log\NullLogger::class);

            return;
        }
        $container->extension('security', [
            'password_hashers' => [Customer::class => $test ? ['algorithm' => 'plaintext'] : 'auto'],
            'providers' => ['astra_customers' => ['entity' => ['class' => Customer::class, 'property' => 'email']]],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'astra_customers',
                    'form_login' => [
                        'login_path' => 'astra_login',
                        'check_path' => 'astra_login',
                        'enable_csrf' => true,
                        'default_target_path' => 'astra_account',
                    ],
                    'logout' => ['path' => 'astra_logout', 'target' => 'astra_home'],
                ],
            ],
        ]);
        if ($test) {
            $container->services()->set('logger', \Psr\Log\NullLogger::class);
        }
        $container->extension('astra_checkout', [
            'shop_name' => 'Astra Demo',
            'mailer' => ['from' => 'shop@example.com'],
            'checkout' => ['terms_url' => '/cgv'],
            'search' => ['synonyms' => ['sweat' => ['hoodie', 'pull']]],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@AstraCheckoutBundle/config/routes.yaml');
        if ('host' === $this->environment) {
            $page = fn (string $name, string $path) => $routes->add($name, $path)->controller('kernel::sitePage');
            $page('app_home', '/');
            $page('app_catalog', '/produits');
            $page('app_category', '/categorie/{slug}');
            $page('app_product', '/produit/{slug}');
            $page('app_search', '/recherche');
            $page('app_cart', '/panier');
            $page('app_checkout', '/commande');
            $page('app_confirmation', '/commande/merci/{token}');
            $page('app_orders', '/mon-compte');
            $page('app_order', '/mon-compte/commande/{number}');
            $page('app_addresses', '/mon-compte/adresses');
            $page('app_address_new', '/mon-compte/adresses/nouvelle');
            $page('app_address_edit', '/mon-compte/adresses/{id}')->requirements(['id' => '\d+']);
            $page('app_register', '/inscription')->defaults(['title' => 'Inscription du site']);
            $routes->add('app_login', '/connexion')->controller('kernel::siteLogin')->methods(['GET', 'POST']);
            $routes->add('app_logout', '/deconnexion')->methods(['GET']);
        }
    }

    /** Pages of the "existing website" used by the host tests. */
    public function sitePage(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        /** @var \Twig\Environment $twig */
        $twig = $this->getContainer()->get('test.service_container')->get('twig');

        return new Response($twig->render('site/page.html.twig', $request->attributes->get('_route_params', []) + ['title' => 'Mon site']));
    }

    public function siteLogin(): Response
    {
        return new Response('<!DOCTYPE html><html lang="fr"><head><title>Connexion</title></head><body><main id="main"><h1>Connexion du site</h1><form method="post" action="/connexion"><label for="u">E-mail</label><input id="u" name="_username"><label for="p">Mot de passe</label><input id="p" type="password" name="_password"><button>Se connecter</button></form></main></body></html>');
    }
}
