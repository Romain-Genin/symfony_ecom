<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use StudioAstra\AstraCheckoutBundle\Entity\Category;
use StudioAstra\AstraCheckoutBundle\Customer\Customer;
use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\ProductImage;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ShopTestCase extends WebTestCase
{
    protected const ENVIRONMENT = 'test';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient(['environment' => static::ENVIRONMENT]);
        $this->client->disableReboot();
        $em = $this->em();
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::getContainer()->get('cache.app')->clear();
        $this->loadFixtures();
    }

    protected function em(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * Clothes > T-shirts: "T-shirt" (3 sizes, M = 2 left, L sold out), "Polo" (on sale)
     * Home: "Mug" (no options, untracked stock), "Lamp" (disabled).
     */
    protected function loadFixtures(): void
    {
        $em = $this->em();
        $clothes = new Category('Vêtements', 'vetements');
        $tshirts = (new Category('T-shirts', 't-shirts'))->setParent($clothes);
        $home = new Category('Maison', 'maison');
        foreach ([$clothes, $tshirts, $home] as $c) {
            $em->persist($c);
        }

        $tshirt = $this->product('T-shirt bleu', 't-shirt-bleu', [$tshirts], [
            ['TS-S', 2500, 10, ['Taille' => 'S']],
            ['TS-M', 2500, 2, ['Taille' => 'M']],
            ['TS-L', 2500, 0, ['Taille' => 'L']],
        ], '-3 days');
        $tshirt->addImage(new ProductImage('uploads/tshirt.jpg', 'T-shirt bleu à plat', 800, 1000));
        $polo = $this->product('Polo', 'polo', [$tshirts], [['PO-1', 3000, 5, []]], '-2 days');
        $polo->getVariants()->first()->setCompareAtPrice(4000);
        $this->product('Mug', 'mug', [$home], [['MUG', 1200, null, []]], '-1 day')->setTaxRate(550);
        $this->product('Lampe', 'lampe', [$home], [['LA', 9900, 3, []]], '-4 days')->setEnabled(false);

        $em->flush();
        $em->clear();
    }

    /** @param list<array{0: string, 1: int, 2: ?int, 3: array<string, string>}> $variants */
    protected function product(string $name, string $slug, array $categories, array $variants, string $created): Product
    {
        $product = new Product($name, $slug);
        $product->setShortDescription("Description de $name");
        foreach ($categories as $c) {
            $product->addCategory($c);
        }
        foreach ($variants as $i => [$sku, $price, $stock, $options]) {
            $product->addVariant((new ProductVariant($sku, $price, $stock, $options))->setPosition($i));
        }
        $product->setCreatedAt(new \DateTimeImmutable($created));
        $this->em()->persist($product);

        return $product;
    }

    protected function variantId(string $sku): int
    {
        return $this->em()->getRepository(ProductVariant::class)->findOneBy(['sku' => $sku])->getId();
    }

    protected function addToCart(string $sku, int $quantity = 1, bool $json = true): array
    {
        $this->client->request('POST', $this->url('astra_cart_add'), ['variant' => $this->variantId($sku), 'quantity' => $quantity], [], $json ? ['HTTP_ACCEPT' => 'application/json'] : []);

        return $json ? json_decode((string) $this->client->getResponse()->getContent(), true) : [];
    }

    protected function createCustomer(string $email = 'alice@example.com', string $password = 'secret-password'): Customer
    {
        $customer = (new Customer())->setEmail($email)->setFirstName('Alice')->setLastName('Martin');
        $customer->setPassword($password); // plaintext hasher in the test kernel
        $this->em()->persist($customer);
        $this->em()->flush();

        return $customer;
    }

    protected function login(string $email = 'alice@example.com', string $password = 'secret-password'): void
    {
        $crawler = $this->client->request('GET', '/account/login');
        $form = $crawler->filter('form[action="/account/login"]')->form(['_username' => $email, '_password' => $password]);
        $this->client->submit($form);
    }

    /** Guest checkout up to the review page. */
    protected function fillCheckout(string $email = 'guest@example.com'): void
    {
        $crawler = $this->client->request('GET', $this->shopUrl('checkout', ['step' => 'address']));
        self::assertResponseIsSuccessful();
        $values = [
            'checkout_address[shippingAddress][firstName]' => 'Jean',
            'checkout_address[shippingAddress][lastName]' => 'Dupont',
            'checkout_address[shippingAddress][street]' => '1 rue de la Paix',
            'checkout_address[shippingAddress][postalCode]' => '75002',
            'checkout_address[shippingAddress][city]' => 'Paris',
            'checkout_address[shippingAddress][countryCode]' => 'FR',
            'checkout_address[shippingAddress][phone]' => '0601020304',
            'checkout_address[billingSameAsShipping]' => true,
        ];
        if ($crawler->filter('input[name="checkout_address[email]"]')->count()) {
            $values['checkout_address[email]'] = $email;
        }
        $this->client->submit($crawler->filter('main form')->form($values));
        self::assertResponseRedirects($this->shopUrl('checkout', ['step' => 'shipping']));

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('main form')->form([
            'shipping_payment[shippingMethod]' => 'standard',
            'shipping_payment[paymentMethod]' => 'bank_transfer',
        ]));
        self::assertResponseRedirects($this->shopUrl('checkout', ['step' => 'review']));
    }

    /** @param array<string, mixed> $parameters */
    protected function shopUrl(string $page, array $parameters = []): string
    {
        return self::getContainer()->get(\StudioAstra\AstraCheckoutBundle\Routing\ShopUrls::class)->path($page, $parameters);
    }

    /** @param array<string, mixed> $parameters */
    protected function url(string $route, array $parameters = []): string
    {
        return self::getContainer()->get('router')->generate($route, $parameters);
    }
}
