<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Search\Meilisearch;

use RomainGenin\AstraCheckoutBundle\Entity\Product;
use RomainGenin\AstraCheckoutBundle\Search\ProductDocumentFactory;

/** Product index: settings, upserts, deletions. */
final class MeilisearchIndex
{
    /**
     * @param array<string, list<string>> $synonyms
     */
    public function __construct(
        private readonly MeilisearchClient $client,
        private readonly ProductDocumentFactory $documents,
        private readonly string $index,
        private readonly array $synonyms = [],
    ) {
    }

    public function getName(): string
    {
        return $this->index;
    }

    /** Creates the index (if needed) and applies the settings. Returns the task uids. */
    public function setup(): array
    {
        $tasks = [];
        try {
            $tasks[] = $this->client->request('POST', '/indexes', ['uid' => $this->index, 'primaryKey' => 'id'])['taskUid'] ?? null;
        } catch (MeilisearchException $e) {
            if ('index_already_exists' !== $e->errorCode) {
                throw $e;
            }
        }

        $synonyms = [];
        foreach ($this->synonyms as $word => $alternatives) {
            $synonyms[$word] = $alternatives;
            foreach ($alternatives as $alternative) {
                $synonyms[$alternative] = array_values(array_diff([$word, ...$alternatives], [$alternative]));
            }
        }

        $tasks[] = $this->client->request('PATCH', "/indexes/{$this->index}/settings", [
            'searchableAttributes' => ['name', 'brand', 'categories', 'options', 'skus', 'shortDescription'],
            'filterableAttributes' => ['categoryIds', 'minPrice', 'available', 'onSale'],
            'sortableAttributes' => ['minPrice', 'createdAt', 'nameSort'],
            'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness', 'available:desc', 'createdAt:desc'],
            'synonyms' => (object) $synonyms,
            'pagination' => ['maxTotalHits' => 10000],
        ])['taskUid'] ?? null;

        return array_values(array_filter($tasks, static fn ($t) => null !== $t));
    }

    /** @param iterable<Product> $products enabled ones are upserted, disabled ones removed */
    public function save(iterable $products): void
    {
        $documents = [];
        $removed = [];
        foreach ($products as $product) {
            if ($product->isEnabled()) {
                $documents[] = $this->documents->document($product);
            } else {
                $removed[] = $product->getId();
            }
        }
        foreach (array_chunk($documents, 500) as $chunk) {
            $this->client->request('POST', "/indexes/{$this->index}/documents", $chunk);
        }
        $this->delete($removed);
    }

    /** @param list<int|null> $ids */
    public function delete(array $ids): void
    {
        $ids = array_values(array_filter($ids));
        if ([] !== $ids) {
            $this->client->request('POST', "/indexes/{$this->index}/documents/delete-batch", $ids);
        }
    }

    public function clear(): void
    {
        $this->client->request('DELETE', "/indexes/{$this->index}/documents");
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<mixed>
     */
    public function search(array $query): array
    {
        return $this->client->request('POST', "/indexes/{$this->index}/search", $query);
    }
}
