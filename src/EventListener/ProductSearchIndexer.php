<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use StudioAstra\AstraCheckoutBundle\Search\ProductDocumentFactory;

/**
 * Keeps Product::searchText in sync in the same flush (no extra query when searching).
 * Renaming a category: run bin/console astra:search:reindex.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class ProductSearchIndexer
{
    public function __construct(private readonly ProductDocumentFactory $documents)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $products = [];
        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates()] as $entity) {
            $product = $entity instanceof Product ? $entity : ($entity instanceof ProductVariant ? $entity->getProduct() : null);
            if (null !== $product && !$uow->isScheduledForDelete($product)) {
                $products[spl_object_id($product)] = $product;
            }
        }
        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            if ($collection->getOwner() instanceof Product) {
                $products[spl_object_id($collection->getOwner())] = $collection->getOwner();
            }
        }

        $meta = $em->getClassMetadata(Product::class);
        foreach ($products as $product) {
            [$name, $text] = $this->documents->databaseIndex($product);
            if ($name === $product->getSearchName() && $text === $product->getSearchText()) {
                continue;
            }
            $product->setSearchIndex($name, $text);
            if ($uow->isScheduledForInsert($product) || $uow->isScheduledForUpdate($product)) {
                $uow->recomputeSingleEntityChangeSet($meta, $product);
            } else {
                $uow->computeChangeSet($meta, $product);
            }
        }
    }
}
