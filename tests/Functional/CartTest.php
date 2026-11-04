<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Functional;

final class CartTest extends ShopTestCase
{
    public function testAddToCartWithJson(): void
    {
        $data = $this->addToCart('TS-S', 2);
        self::assertResponseIsSuccessful();
        self::assertTrue($data['ok']);
        self::assertSame(2, $data['cart']['count']);
        self::assertStringContainsString('T-shirt bleu – S', $data['message']);
        self::assertSame('50,00 €', preg_replace('/\s/u', ' ', $data['cart']['subtotal']));

        $this->addToCart('TS-S', 1);
        $this->client->request('GET', '/cart/summary');
        $summary = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(3, $summary['count']);
        self::assertCount(1, $summary['items']);
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    public function testAddToCartWithoutJavascriptRedirectsToCart(): void
    {
        $this->addToCart('MUG', 1, false);
        self::assertResponseRedirects('/cart', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main [role="status"]', 'Mug');
        self::assertSelectorTextContains('main', '12,00');
    }

    public function testStockIsEnforced(): void
    {
        $data = $this->addToCart('TS-M', 3);
        self::assertResponseStatusCodeSame(422);
        self::assertFalse($data['ok']);
        self::assertStringContainsString('Il ne reste que 2', $data['message']);

        $data = $this->addToCart('TS-L', 1);
        self::assertStringContainsString('épuisé', $data['message']);
    }

    public function testDisabledProductCannotBeAdded(): void
    {
        $data = $this->addToCart('LA');
        self::assertResponseStatusCodeSame(422);
        self::assertFalse($data['ok']);
    }

    public function testUpdateAndRemove(): void
    {
        $data = $this->addToCart('TS-S', 1);
        $id = $data['cart']['items'][0]['id'];

        $this->client->request('POST', "/cart/items/$id", ['quantity' => 4], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(4, json_decode($this->client->getResponse()->getContent(), true)['cart']['count']);

        $this->client->request('POST', "/cart/items/$id/remove", [], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(0, json_decode($this->client->getResponse()->getContent(), true)['cart']['count']);

        $this->client->request('GET', '/cart');
        self::assertSelectorTextContains('main', 'Votre panier est vide');
    }

    public function testCrossSiteRequestsAreRejected(): void
    {
        $this->client->request('POST', '/cart/add', ['variant' => $this->variantId('MUG')], [], ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCartRevalidationWarnsAboutStockChanges(): void
    {
        $this->addToCart('TS-S', 5);
        $variant = $this->em()->getRepository(\StudioAstra\AstraCheckoutBundle\Entity\ProductVariant::class)->findOneBy(['sku' => 'TS-S']);
        $variant->setStock(3);
        $this->em()->flush();

        $this->client->request('GET', '/cart');
        self::assertSelectorTextContains('main [role="status"]', 'ramenée à 3');
    }
}
