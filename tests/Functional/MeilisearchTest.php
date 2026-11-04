<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Tests\Functional;

use Doctrine\ORM\Events;
use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Repository\ProductRepository;
use StudioAstra\AstraCheckoutBundle\Search\DatabaseSearchEngine;
use StudioAstra\AstraCheckoutBundle\Search\Meilisearch\MeilisearchClient;
use StudioAstra\AstraCheckoutBundle\Search\Meilisearch\MeilisearchIndex;
use StudioAstra\AstraCheckoutBundle\Search\Meilisearch\MeilisearchIndexer;
use StudioAstra\AstraCheckoutBundle\Search\Meilisearch\MeilisearchSearchEngine;
use StudioAstra\AstraCheckoutBundle\Search\ProductDocumentFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MeilisearchTest extends ShopTestCase
{
    /** @var list<array{method: string, url: string, body: mixed}> */
    private array $requests = [];

    /** @param list<MockResponse>|callable $responses */
    private function index(array|callable $responses): MeilisearchIndex
    {
        $queue = $responses;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => isset($options['body']) ? json_decode($options['body'], true) : null, 'headers' => $options['normalized_headers'] ?? []];

            return \is_callable($queue) ? $queue($method, $url) : (array_shift($queue) ?? new MockResponse('{}'));
        });

        return new MeilisearchIndex(new MeilisearchClient($http, 'http://meili:7700', 'search-key'), new ProductDocumentFactory(), 'shop_products', ['sweat' => ['hoodie']]);
    }

    private function engine(MeilisearchIndex $index): MeilisearchSearchEngine
    {
        $repository = self::getContainer()->get(ProductRepository::class);

        return new MeilisearchSearchEngine($index, $repository, new DatabaseSearchEngine($repository));
    }

    private function id(string $slug): int
    {
        return $this->em()->getRepository(Product::class)->findOneBy(['slug' => $slug])->getId();
    }

    public function testSearchSendsFiltersAndSortAndHydratesInOrder(): void
    {
        $polo = $this->id('polo');
        $tshirt = $this->id('t-shirt-bleu');
        $engine = $this->engine($this->index([new MockResponse(json_encode([
            'hits' => [['id' => $polo], ['id' => $tshirt]],
            'estimatedTotalHits' => 2,
            'facetStats' => ['minPrice' => ['min' => 2500, 'max' => 3000]],
        ]))]));

        $page = $engine->search(new ProductListCriteria(categoryIds: [3, 4], search: 'tshrit', minPrice: 1000, inStockOnly: true, sort: 'price_desc', page: 2, perPage: 10));

        self::assertSame(['Polo', 'T-shirt bleu'], array_map(fn ($p) => $p->getName(), $page->products));
        self::assertSame(2, $page->total);
        self::assertSame(['min' => 2500, 'max' => 3000], $page->priceRange);

        $request = $this->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('http://meili:7700/indexes/shop_products/search', $request['url']);
        self::assertSame('tshrit', $request['body']['q']);
        self::assertSame(['categoryIds IN [3, 4]', 'minPrice >= 1000', 'available = 1'], $request['body']['filter']);
        self::assertSame(['minPrice:desc'], $request['body']['sort']);
        self::assertSame(10, $request['body']['offset']);
        self::assertSame(['Authorization: Bearer search-key'], $request['headers']['authorization']);
    }

    public function testFallsBackToTheDatabaseWhenMeilisearchIsDown(): void
    {
        $engine = $this->engine($this->index([new MockResponse('', ['http_code' => 503])]));
        $page = $engine->search(new ProductListCriteria(search: 'mug'));
        self::assertSame(['Mug'], array_map(fn ($p) => $p->getName(), $page->products));

        $engine = $this->engine($this->index(fn () => throw new \RuntimeException('Connection refused')));
        self::assertSame(['Mug'], array_map(fn ($p) => $p->getName(), $engine->suggest('mug', 5)));
    }

    public function testSetupConfiguresTheIndex(): void
    {
        $this->index([
            new MockResponse('{"code":"index_already_exists","message":"exists"}', ['http_code' => 409]),
            new MockResponse('{"taskUid": 12}'),
        ])->setup();

        $settings = $this->requests[1]['body'];
        self::assertSame('PATCH', $this->requests[1]['method']);
        self::assertContains('categoryIds', $settings['filterableAttributes']);
        self::assertContains('available:desc', $settings['rankingRules']);
        self::assertSame(['hoodie'], $settings['synonyms']['sweat']);
        self::assertSame(['sweat'], $settings['synonyms']['hoodie']);
    }

    public function testIndexerPushesChangesAfterFlush(): void
    {
        $indexer = new MeilisearchIndexer($this->index([]));
        $events = $this->em()->getEventManager();
        $events->addEventListener([Events::onFlush, Events::postFlush], $indexer);

        $product = $this->em()->getRepository(Product::class)->findOneBy(['slug' => 'mug']);
        $product->getVariants()->first()->setPrice(1500);
        $this->em()->flush();

        self::assertCount(1, $this->requests);
        self::assertSame('http://meili:7700/indexes/shop_products/documents', $this->requests[0]['url']);
        $document = $this->requests[0]['body'][0];
        self::assertSame($product->getId(), $document['id']);
        self::assertSame(1500, $document['minPrice']);
        self::assertSame(['Maison'], $document['categories']);
        self::assertSame(['MUG'], $document['skus']);

        // Disabling removes it from the index.
        $this->requests = [];
        $product->setEnabled(false);
        $this->em()->flush();
        self::assertStringEndsWith('/documents/delete-batch', $this->requests[0]['url']);
        self::assertSame([$product->getId()], $this->requests[0]['body']);

        $events->removeEventListener([Events::onFlush, Events::postFlush], $indexer);
    }

    public function testIndexingErrorsNeverBreakTheRequest(): void
    {
        $indexer = new MeilisearchIndexer($this->index(fn () => new MockResponse('', ['http_code' => 500])));
        $events = $this->em()->getEventManager();
        $events->addEventListener([Events::onFlush, Events::postFlush], $indexer);
        $this->em()->getRepository(Product::class)->findOneBy(['slug' => 'mug'])->setName('Mug XL');
        $this->em()->flush();
        $events->removeEventListener([Events::onFlush, Events::postFlush], $indexer);
        self::assertCount(1, $this->requests);
    }
}
