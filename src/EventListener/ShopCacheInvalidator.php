<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use StudioAstra\AstraCheckoutBundle\Announcement\AnnouncementProvider;
use StudioAstra\AstraCheckoutBundle\Entity\Announcement;
use StudioAstra\AstraCheckoutBundle\Entity\Category;
use StudioAstra\AstraCheckoutBundle\Entity\Promotion;
use StudioAstra\AstraCheckoutBundle\Promotion\PromotionProvider;
use StudioAstra\AstraCheckoutBundle\Twig\AstraCheckoutExtension;
use Symfony\Contracts\Cache\CacheInterface;

/** Drops the cached menu / promotions / announcements as soon as one of them changes. */
#[AsDoctrineListener(event: Events::onFlush)]
final class ShopCacheInvalidator
{
    private const KEYS = [
        Category::class => AstraCheckoutExtension::CATEGORY_TREE_CACHE_KEY,
        Promotion::class => PromotionProvider::CACHE_KEY,
        Announcement::class => AnnouncementProvider::CACHE_KEY,
    ];

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $keys = [];
        foreach ([$uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates(), $uow->getScheduledEntityDeletions()] as $entities) {
            foreach ($entities as $entity) {
                foreach (self::KEYS as $class => $key) {
                    if ($entity instanceof $class) {
                        $keys[$key] = true;
                    }
                }
            }
        }
        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            $owner = $collection->getOwner();
            if ($owner instanceof Promotion) {
                $keys[PromotionProvider::CACHE_KEY] = true;
            }
        }
        foreach (array_keys($keys) as $key) {
            $this->cache->delete($key);
        }
    }
}
