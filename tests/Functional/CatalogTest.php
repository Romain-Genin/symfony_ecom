<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Functional;

final class CatalogTest extends ShopTestCase
{
    public function testHomePage(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'essentiels');
        self::assertSelectorExists('a[href="/c/vetements"]');
    }

    public function testListingShowsEnabledProductsNewestFirst(): void
    {
        $crawler = $this->client->request('GET', '/products');
        self::assertResponseIsSuccessful();
        self::assertSame(['Mug', 'Polo', 'T-shirt bleu'], $crawler->filter('main article h3')->each(fn ($n) => trim($n->text())));
        self::assertSelectorTextContains('main', '3 produits');
    }

    public function testCategoryIncludesSubcategories(): void
    {
        $crawler = $this->client->request('GET', '/c/vetements');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vêtements');
        self::assertCount(2, $crawler->filter('main article'));
        self::assertSelectorExists('nav[aria-label="Sous-catégories"] a[href="/c/t-shirts"]');
    }

    public function testFiltersAndSort(): void
    {
        $crawler = $this->client->request('GET', '/products?sort=price_asc');
        self::assertSame(['Mug', 'T-shirt bleu', 'Polo'], $crawler->filter('main article h3')->each(fn ($n) => trim($n->text())));

        $crawler = $this->client->request('GET', '/products?min=20&max=26');
        self::assertSame(['T-shirt bleu'], $crawler->filter('main article h3')->each(fn ($n) => trim($n->text())));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, follow');

        $crawler = $this->client->request('GET', '/products?on_sale=1');
        self::assertSame(['Polo'], $crawler->filter('main article h3')->each(fn ($n) => trim($n->text())));
        self::assertSelectorTextContains('main', 'Tout effacer');
    }

    public function testSearch(): void
    {
        $crawler = $this->client->request('GET', '/search?q=t-shirt');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', '« t-shirt »');
        // The polo is in the "T-shirts" category: found too, but ranked after the name match.
        self::assertSame(['T-shirt bleu', 'Polo'], $crawler->filter('main article h3')->each(fn ($n) => trim($n->text())));

        $this->client->request('GET', '/search?q=introuvable');
        self::assertSelectorTextContains('main', 'Aucun produit');
    }

    public function testPaginationRedirectsOutOfRangePages(): void
    {
        $this->client->request('GET', '/products?page=40');
        self::assertResponseRedirects('/products');
    }

    public function testCatalogPagesArePubliclyCacheableAndRevalidated(): void
    {
        $this->client->request('GET', '/c/t-shirts');
        $response = $this->client->getResponse();
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('300', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertNotNull($etag = $response->getEtag());
        self::assertSame([], $response->headers->getCookies(), 'A cacheable page must not set cookies.');

        $this->client->request('GET', '/c/t-shirts', [], [], ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
    }

    public function testProductPage(): void
    {
        $crawler = $this->client->request('GET', '/p/t-shirt-bleu');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'T-shirt bleu');
        self::assertSelectorExists('link[rel="canonical"][href$="/p/t-shirt-bleu"]');

        $jsonLd = array_map(fn ($s) => json_decode($s, true), $crawler->filter('script[type="application/ld+json"]')->each(fn ($n) => $n->text()));
        $product = array_values(array_filter($jsonLd, fn ($d) => 'Product' === $d['@type']))[0];
        self::assertSame('25.00', $product['offers'][0]['price']);
        self::assertSame('https://schema.org/OutOfStock', $product['offers'][2]['availability']);

        // No-JS fallback: sold-out variants are disabled in the select.
        self::assertSelectorExists('select[name="variant"] option[disabled]');
        self::assertSelectorTextContains('main', 'En stock');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('public'));
    }

    public function testDisabledOrUnknownProductIs404(): void
    {
        $this->client->request('GET', '/p/lampe');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/c/nope');
        self::assertResponseStatusCodeSame(404);
    }
}
