<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Search;

use StudioAstra\AstraCheckoutBundle\Entity\Category;
use StudioAstra\AstraCheckoutBundle\Entity\Product;

/** What is searchable about a product, for every engine. */
final class ProductDocumentFactory
{
    /** @return array<string, mixed> */
    public function document(Product $product): array
    {
        $categories = [];
        foreach ($product->getCategories() as $category) {
            foreach ([...$category->getAncestors(), $category] as $c) {
                $categories[$c->getId()] = $c;
            }
        }
        $options = [];
        $skus = [];
        foreach ($product->getVariants() as $variant) {
            $skus[] = $variant->getSku();
            foreach ($variant->getOptions() as $value) {
                $options[] = $value;
            }
        }

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'nameSort' => TextNormalizer::normalize($product->getName()),
            'brand' => $product->getBrand(),
            'shortDescription' => strip_tags((string) $product->getShortDescription()),
            'categories' => array_values(array_map(static fn (Category $c): string => $c->getName(), $categories)),
            'categoryIds' => array_values(array_map(static fn (Category $c): int => (int) $c->getId(), $categories)),
            'options' => array_values(array_unique($options)),
            'skus' => $skus,
            'minPrice' => $product->getMinPrice(),
            'available' => $product->isInStock() ? 1 : 0,
            'onSale' => $product->isOnSale() ? 1 : 0,
            'createdAt' => $product->getCreatedAt()->getTimestamp(),
        ];
    }

    /** @return array{0: string, 1: string} normalized name and full text for the database engine */
    public function databaseIndex(Product $product): array
    {
        $doc = $this->document($product);

        return [
            TextNormalizer::forIndex($product->getName()),
            TextNormalizer::forIndex($doc['name'], (string) $doc['brand'], $doc['shortDescription'], ...$doc['categories'], ...$doc['options'], ...$doc['skus']),
        ];
    }
}
