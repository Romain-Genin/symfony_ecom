<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Promotion;

use Psr\Clock\ClockInterface;
use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\Promotion;
use StudioAstra\AstraCheckoutBundle\Repository\PromotionRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Active promotions. The list of automatic promotions is cached (plain descriptors, invalidated
 * on every change): when no promotion is running, computing a cart costs no query at all.
 */
final class PromotionProvider implements ResetInterface
{
    public const CACHE_KEY = 'astra_checkout.automatic_promotions';

    /** @var list<Promotion>|null */
    private ?array $active = null;

    public function __construct(
        private readonly PromotionRepository $repository,
        private readonly CacheInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<Promotion> automatic promotions running now, highest priority first */
    public function getActiveAutomatic(): array
    {
        if (null !== $this->active) {
            return $this->active;
        }
        $ids = array_column($this->activeDescriptors(), 'id');
        $now = $this->now();

        return $this->active = array_values(array_filter(
            $this->repository->findByIds($ids),
            static fn (Promotion $p): bool => $p->isActiveAt($now),
        ));
    }

    public function findCoupon(string $code): ?Promotion
    {
        $promotion = $this->repository->findOneByCode($code);

        return null !== $promotion && !$promotion->isAutomatic() ? $promotion : null;
    }

    /** @return list<Promotion> automatic promotions to advertise on this product page */
    public function forProduct(Product $product): array
    {
        return array_values(array_filter(
            $this->getActiveAutomatic(),
            fn (Promotion $p): bool => $p->isShowOnProductPage() && !($p->isExcludeSaleItems() && $product->isOnSale()) && $this->appliesTo($p, $product),
        ));
    }

    public function appliesTo(Promotion $promotion, Product $product): bool
    {
        if (!$promotion->hasScope()) {
            return true;
        }
        foreach ($promotion->getProducts() as $p) {
            if ($p->getId() === $product->getId()) {
                return true;
            }
        }
        $scope = array_map(static fn ($c) => $c->getId(), $promotion->getCategories()->toArray());
        foreach ($product->getCategories() as $category) {
            foreach ([$category, ...$category->getAncestors()] as $c) {
                if (\in_array($c->getId(), $scope, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Changes whenever the set of running automatic promotions changes (used in ETags). */
    public function versionKey(): string
    {
        return implode(',', array_map(static fn (array $d): string => $d['id'] . ':' . $d['version'], $this->activeDescriptors()));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function reset(): void
    {
        $this->active = null;
    }

    /** @return list<array<string, mixed>> */
    private function activeDescriptors(): array
    {
        $all = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return $this->repository->findEnabledAutomaticDescriptors();
        });
        $now = $this->now()->getTimestamp();

        return array_values(array_filter($all, static fn (array $d): bool => !$d['usedUp']
            && (null === $d['startsAt'] || $d['startsAt'] <= $now)
            && (null === $d['endsAt'] || $d['endsAt'] > $now)));
    }
}
