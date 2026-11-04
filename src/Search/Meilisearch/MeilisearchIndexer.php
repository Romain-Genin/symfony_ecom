<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Search\Meilisearch;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Psr\Log\LoggerInterface;
use RomainGenin\AstraCheckoutBundle\Entity\Product;
use RomainGenin\AstraCheckoutBundle\Entity\ProductVariant;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Pushes product changes to Meilisearch right after each flush (prices, stock, names…).
 * An unreachable Meilisearch never breaks the request: the error is logged, run
 * bin/console astra:search:reindex to resynchronize.
 */
final class MeilisearchIndexer implements ResetInterface
{
    /** @var array<int, Product> */
    private array $changed = [];

    /** @var list<int> */
    private array $deleted = [];

    public function __construct(private readonly MeilisearchIndex $index, private readonly ?LoggerInterface $logger = null)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates()] as $entity) {
            $product = $entity instanceof Product ? $entity : ($entity instanceof ProductVariant ? $entity->getProduct() : null);
            if (null !== $product) {
                $this->changed[spl_object_id($product)] = $product;
            }
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Product && null !== $entity->getId()) {
                $this->deleted[] = $entity->getId();
            } elseif ($entity instanceof ProductVariant && null !== $entity->getProduct() && !$uow->isScheduledForDelete($entity->getProduct())) {
                $this->changed[spl_object_id($entity->getProduct())] = $entity->getProduct();
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->changed && [] === $this->deleted) {
            return;
        }
        $changed = array_values($this->changed);
        $deleted = $this->deleted;
        $this->reset();

        try {
            $this->index->save($changed);
            $this->index->delete($deleted);
        } catch (MeilisearchException $e) {
            $this->logger?->error('AstraCheckout: Meilisearch indexing failed (run astra:search:reindex): {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }

    public function reset(): void
    {
        $this->changed = [];
        $this->deleted = [];
    }
}
