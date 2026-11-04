<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle;

use RomainGenin\AstraCheckoutBundle\Customer\Customer;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInterface;
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodInterface;
use RomainGenin\AstraCheckoutBundle\Routing\ShopRouteLoader;
use RomainGenin\AstraCheckoutBundle\Routing\ShopUrls;
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodRegistry;
use RomainGenin\AstraCheckoutBundle\Pricing\MoneyFormatter;
use RomainGenin\AstraCheckoutBundle\Search\DatabaseSearchEngine;
use RomainGenin\AstraCheckoutBundle\Search\Meilisearch\MeilisearchClient;
use RomainGenin\AstraCheckoutBundle\Search\Meilisearch\MeilisearchIndex;
use RomainGenin\AstraCheckoutBundle\Search\Meilisearch\MeilisearchIndexer;
use RomainGenin\AstraCheckoutBundle\Search\Meilisearch\MeilisearchSearchEngine;
use RomainGenin\AstraCheckoutBundle\Search\ProductDocumentFactory;
use RomainGenin\AstraCheckoutBundle\Search\SearchEngineInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodInterface;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class AstraCheckoutBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode()->children();

        $root
            ->scalarNode('shop_name')->defaultValue('Astra Shop')->end()
            ->scalarNode('currency')->defaultValue('EUR')->info('ISO 4217 code. Amounts are stored in minor units (cents).')->end()
            ->booleanNode('prices_include_tax')->defaultTrue()->info('true: catalog prices are tax-inclusive (B2C, EU). false: taxes are added at checkout (B2B, US).')->end()
            ->integerNode('default_tax_rate')->defaultValue(2000)->min(0)->info('Basis points (2000 = 20 %), used by the demo loader and as a suggestion for new products.')->end()
            ->scalarNode('layout')->defaultValue('@AstraCheckout/base.html.twig')->info('Template every shop page extends. Point it to your own layout (it must define the blocks of base.html.twig).')->end();

        $root->arrayNode('catalog')
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('per_page')->defaultValue(24)->min(1)->max(120)->end()
                ->integerNode('low_stock_threshold')->defaultValue(5)->min(0)->info('"Only N left" is shown at or below this stock.')->end()
                ->arrayNode('http_cache')
                    ->addDefaultsIfNotSet()
                    ->info('PLP/PDP contain no personal data: they are sent with public Cache-Control headers and an ETag.')
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('max_age')->defaultValue(0)->min(0)->info('Browser cache (seconds). 0 = always revalidate (cheap 304 thanks to the ETag).')->end()
                        ->integerNode('shared_max_age')->defaultValue(300)->min(0)->info('Reverse proxy / CDN cache (seconds).')->end()
                    ->end()
                ->end()
            ->end();

        $root->arrayNode('cart')
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('max_quantity_per_item')->defaultValue(99)->min(1)->end()
                ->integerNode('lifetime_days')->defaultValue(30)->min(1)->info('Anonymous carts older than this are deleted by astra:cart:purge.')->end()
            ->end();

        $root->arrayNode('checkout')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('guest')->defaultTrue()->info('Allow ordering without an account.')->end()
                ->arrayNode('countries')->scalarPrototype()->end()->defaultValue(['FR', 'BE', 'LU', 'CH', 'MC', 'DE', 'ES', 'IT', 'NL', 'PT'])->info('Delivery countries (ISO 3166-1 alpha-2), first = default. Empty = all.')->end()
                ->scalarNode('order_number_prefix')->defaultValue('AS')->end()
                ->scalarNode('terms_url')->defaultNull()->info('URL of your terms and conditions, linked from the "I accept" checkbox.')->end()
            ->end();

        $root->arrayNode('shipping')
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('tax_rate')->defaultValue(2000)->min(0)->info('VAT applied to shipping costs (basis points).')->end()
                ->arrayNode('methods')
                    ->info('Flat-rate methods. Add carriers with an API by implementing ShippingMethodInterface.')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->isRequired()->end()
                            ->scalarNode('description')->defaultNull()->end()
                            ->integerNode('price')->defaultValue(0)->min(0)->end()
                            ->integerNode('free_above')->defaultNull()->end()
                            ->arrayNode('countries')->scalarPrototype()->end()->defaultValue([])->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'standard' => ['label' => 'shipping.standard.label', 'description' => 'shipping.standard.description', 'price' => 490, 'free_above' => 6000, 'countries' => []],
                        'express' => ['label' => 'shipping.express.label', 'description' => 'shipping.express.description', 'price' => 990, 'free_above' => null, 'countries' => ['FR', 'BE', 'LU', 'MC']],
                    ])
                ->end()
            ->end();

        $root->arrayNode('payment')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('offline_methods')
                    ->info('Payments confirmed manually (bin/console astra:order:status <number> paid). PSPs: implement PaymentMethodInterface.')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->canBeDisabled()
                        ->children()
                            ->scalarNode('label')->isRequired()->end()
                            ->scalarNode('description')->defaultNull()->end()
                            ->scalarNode('instructions')->defaultNull()->info('Shown after the order. Placeholders: {number}, {total}.')->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'bank_transfer' => ['enabled' => true, 'label' => 'payment.bank_transfer.label', 'description' => 'payment.bank_transfer.description', 'instructions' => 'payment.bank_transfer.instructions'],
                    ])
                ->end()
            ->end();

        $root->arrayNode('customer')
            ->addDefaultsIfNotSet()
            ->info('Who the customers are. Set "class" to your own user entity to reuse your existing accounts (no change to the entity needed).')
            ->children()
                ->scalarNode('class')->defaultValue(Customer::class)->end()
                ->arrayNode('properties')
                    ->addDefaultsIfNotSet()
                    ->info('Properties (or getters) of your user entity read by the shop. null = not available.')
                    ->children()
                        ->scalarNode('email')->defaultValue('email')->end()
                        ->scalarNode('first_name')->defaultValue('firstName')->end()
                        ->scalarNode('last_name')->defaultValue('lastName')->end()
                        ->scalarNode('phone')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();

        $root->arrayNode('pages')
            ->addDefaultsIfNotSet()
            ->info('Your own pages (route names) built with the shop blocks. Links of the shop point to them and the matching shop pages are not registered. null = the shop page.')
            ->children()
                ->scalarNode('home')->defaultNull()->end()
                ->scalarNode('catalog')->defaultNull()->end()
                ->scalarNode('category')->defaultNull()->info('Route with a {slug} parameter.')->end()
                ->scalarNode('product')->defaultNull()->info('Route with a {slug} parameter.')->end()
                ->scalarNode('search')->defaultNull()->end()
                ->scalarNode('cart')->defaultNull()->end()
                ->scalarNode('checkout')->defaultNull()->info('Route accepting POST; the step is passed as ?step=.')->end()
                ->scalarNode('confirmation')->defaultNull()->info('Route with a {token} parameter.')->end()
                ->scalarNode('account')->defaultNull()->end()
                ->scalarNode('orders')->defaultNull()->end()
                ->scalarNode('order')->defaultNull()->info('Route with a {number} parameter.')->end()
                ->scalarNode('addresses')->defaultNull()->end()
                ->scalarNode('address_new')->defaultNull()->info('Route accepting POST.')->end()
                ->scalarNode('address_edit')->defaultNull()->info('Route accepting POST, with an {id} parameter.')->end()
            ->end();

        $root->arrayNode('routing')
            ->addDefaultsIfNotSet()
            ->info('URLs of the shop. No prefix needed: only the home route could collide with your site, and it can be disabled.')
            ->children()
                ->booleanNode('home')->defaultTrue()->info('Register the shop home page on "/" (disable it when your site has its own home page).')->end()
                ->arrayNode('paths')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('catalog')->defaultValue('/products')->end()
                        ->scalarNode('search')->defaultValue('/search')->end()
                        ->scalarNode('category')->defaultValue('/c')->end()
                        ->scalarNode('product')->defaultValue('/p')->end()
                        ->scalarNode('cart')->defaultValue('/cart')->end()
                        ->scalarNode('checkout')->defaultValue('/checkout')->end()
                        ->scalarNode('account')->defaultValue('/account')->end()
                    ->end()
                ->end()
            ->end();

        $root->arrayNode('account')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('auth')->defaultNull()->info('Provide the login, registration, password reset, profile and account deletion pages. Default: only with the built-in Customer (your site keeps its own when you reuse your users).')->end()
                ->scalarNode('role')->defaultValue('ROLE_USER')->info('Role required to see "my orders" / "my addresses".')->end()
                ->scalarNode('login_route')->defaultNull()->info('Your login route (default: astra_login, or app_login when reusing your users).')->end()
                ->scalarNode('logout_route')->defaultNull()->info('Your logout route (default: astra_logout, or app_logout).')->end()
                ->scalarNode('register_route')->defaultNull()->info('Your registration route, linked after a guest order (null = no link).')->end()
                ->booleanNode('registration')->defaultTrue()->end()
                ->integerNode('password_reset_ttl')->defaultValue(3600)->min(300)->info('Validity of password reset links (seconds).')->end()
            ->end();

        $root->arrayNode('search')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('engine')->defaultValue('database')->info('"database" (built in), "meilisearch", or the id of a service implementing SearchEngineInterface.')->end()
                ->integerNode('min_length')->defaultValue(2)->min(1)->info('Minimum length of a search-as-you-type query.')->end()
                ->integerNode('suggest_limit')->defaultValue(6)->min(1)->max(20)->end()
                ->arrayNode('synonyms')
                    ->info('e.g. {tshirt: [tee-shirt, tee], sweat: [hoodie, pull]} — used by both engines.')
                    ->useAttributeAsKey('word')
                    ->arrayPrototype()->scalarPrototype()->end()->end()
                ->end()
                ->arrayNode('meilisearch')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')->defaultValue('http://127.0.0.1:7700')->end()
                        ->scalarNode('api_key')->defaultNull()->info('A key with search + documents rights (never the master key in production).')->end()
                        ->scalarNode('index')->defaultValue('astra_products')->end()
                        ->floatNode('timeout')->defaultValue(2.0)->info('Seconds; beyond, the database engine answers instead.')->end()
                    ->end()
                ->end()
            ->end();

        $root->arrayNode('assets')
            ->addDefaultsIfNotSet()
            ->info('How the shop front-end is loaded. With Tailwind: css: false and import astra.css in your build (see README).')
            ->children()
                ->booleanNode('css')->defaultTrue()->info('Load the stand-alone public/astra-checkout.css (false when your Tailwind build imports astra.css).')->end()
                ->enumNode('js')->values(['bundled', 'components', 'none'])->defaultValue('bundled')->info('"bundled": Alpine.js included; "components": your site loads Alpine with a <script> tag; "none": you import components.js in your JS build.')->end()
                ->booleanNode('auto_inject')->defaultTrue()->info('Add the shop assets, mini-cart and configuration to shop pages rendered in your own layout, without editing it.')->end()
            ->end();

        $root->arrayNode('announcements')
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('rotation_interval')->defaultValue(6)->min(0)->info('Seconds between top-bar messages (0 = no automatic rotation). Rotation pauses on hover/focus, has a pause button and is disabled for "reduce motion" users.')->end()
            ->end();

        $root->arrayNode('mailer')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('from')->defaultNull()->info('Sender of transactional e-mails. null = no e-mail (needs symfony/mailer).')->end()
                ->scalarNode('from_name')->defaultNull()->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $customerClass = Customer::class;
        foreach ($builder->getExtensionConfig('astra_checkout') as $config) {
            $customerClass = $builder->getParameterBag()->resolveValue($config['customer']['class'] ?? $customerClass);
        }

        $mappings = [
            'AstraCheckoutBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__ . '/Entity',
                'prefix' => 'RomainGenin\AstraCheckoutBundle\Entity',
                'alias' => 'AstraCheckout',
            ],
        ];
        // The built-in customer table only exists when your own users are not reused.
        if (Customer::class === $customerClass) {
            $mappings['AstraCheckoutCustomer'] = [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__ . '/Customer',
                'prefix' => 'RomainGenin\AstraCheckoutBundle\Customer',
                'alias' => 'AstraCheckoutCustomer',
            ];
        }

        // AssetMapper: expose the JS/CSS sources as "@romain-genin/symfony-ecom/…" (import from your app.js).
        if (interface_exists(\Symfony\Component\AssetMapper\AssetMapperInterface::class)) {
            foreach ($builder->getExtensionConfig('framework') as $framework) {
                if (isset($framework['asset_mapper']) && false !== ($framework['asset_mapper']['enabled'] ?? true)) {
                    $builder->prependExtensionConfig('framework', ['asset_mapper' => ['paths' => [\dirname(__DIR__) . '/assets' => '@romain-genin/symfony-ecom']]]);
                    break;
                }
            }
        }

        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => $mappings,
                // Orders, carts and addresses point to "the customer": your user entity or the built-in one.
                'resolve_target_entities' => [CustomerInterface::class => $customerClass],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services()->defaults()->autowire()->autoconfigure()->private();

        $services->load('RomainGenin\\AstraCheckoutBundle\\', __DIR__ . '/')
            ->exclude([
                __DIR__ . '/{Entity,Enum,Event,Exception,Catalog,AstraCheckoutBundle.php,ShopSettings.php}',
                __DIR__ . '/Pricing/Totals.php',
                __DIR__ . '/Shipping/ConfiguredShippingMethod.php',
                __DIR__ . '/Payment/OfflinePaymentMethod.php',
                __DIR__ . '/Form/NewPasswordOptions.php',
                __DIR__ . '/Customer/{Customer,CustomerInterface}.php',
                __DIR__ . '/Presentation/ListingView.php',
                __DIR__ . '/Twig/Components/CustomerAware.php',
                __DIR__ . '/Promotion/{AppliedDiscount,AppliedPromotions}.php',
                __DIR__ . '/Announcement/AnnouncementView.php',
                __DIR__ . '/Search/{TextNormalizer,SearchEngineInterface}.php',
                __DIR__ . '/Search/Meilisearch',
            ]);

        $search = $config['search'];
        $services->set(DatabaseSearchEngine::class)->arg('$synonyms', $search['synonyms']);
        if ('meilisearch' === $search['engine']) {
            if (!interface_exists(HttpClientInterface::class)) {
                throw new \LogicException('astra_checkout.search.engine "meilisearch" requires symfony/http-client: composer require symfony/http-client');
            }
            $m = $search['meilisearch'];
            $services->set(MeilisearchClient::class)->args([service(HttpClientInterface::class), $m['url'], $m['api_key'], $m['timeout']]);
            $services->set(MeilisearchIndex::class)->args([service(MeilisearchClient::class), service(ProductDocumentFactory::class), $m['index'], $search['synonyms']]);
            $services->set(MeilisearchSearchEngine::class)->autowire();
            $services->set(MeilisearchIndexer::class)->autowire()
                ->tag('doctrine.event_listener', ['event' => 'onFlush'])
                ->tag('doctrine.event_listener', ['event' => 'postFlush'])
                ->tag('kernel.reset', ['method' => 'reset']);
            $services->alias(SearchEngineInterface::class, MeilisearchSearchEngine::class);
        } elseif ('database' === $search['engine']) {
            $services->alias(SearchEngineInterface::class, DatabaseSearchEngine::class);
        } else {
            $services->alias(SearchEngineInterface::class, $search['engine']);
        }

        $auth = $config['account']['auth'] ?? (Customer::class === $config['customer']['class']);
        $config['account']['auth'] = $auth;
        $config['account']['login_route'] ??= $auth ? 'astra_login' : 'app_login';
        $config['account']['logout_route'] ??= $auth ? 'astra_logout' : 'app_logout';
        $config['account']['register_route'] ??= $auth && $config['account']['registration'] ? 'astra_register' : null;
        if (!class_exists($config['customer']['class'])) {
            throw new \InvalidArgumentException(sprintf('astra_checkout.customer.class: class "%s" does not exist.', $config['customer']['class']));
        }

        $services->set(ShopSettings::class)->args([$config]);
        $services->set(CustomerInfo::class)->args([$config['customer']['class'], $config['customer']['properties']]);
        $services->set(ShopRouteLoader::class)
            ->args([$config['routing']['home'], $config['routing']['paths'], $auth, ShopUrls::replacedRoutes($config['pages'])])
            ->tag('routing.loader');
        $services->set(ShopUrls::class)->arg('$pages', $config['pages']);

        $services->set(MoneyFormatter::class)->arg('$defaultLocale', '%kernel.default_locale%');

        $services->set(ShippingMethodRegistry::class)
            ->args([$config['shipping']['methods'], tagged_iterator('astra_checkout.shipping_method')]);
        $services->set(PaymentMethodRegistry::class)
            ->args([$config['payment']['offline_methods'], tagged_iterator('astra_checkout.payment_method')]);

        $builder->registerForAutoconfiguration(ShippingMethodInterface::class)->addTag('astra_checkout.shipping_method');
        $builder->registerForAutoconfiguration(PaymentMethodInterface::class)->addTag('astra_checkout.payment_method');
    }
}
