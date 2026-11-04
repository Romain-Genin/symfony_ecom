<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Announcement;

use Psr\Clock\ClockInterface;
use StudioAstra\AstraCheckoutBundle\Entity\Announcement;
use StudioAstra\AstraCheckoutBundle\Enum\AnnouncementPlacement;
use StudioAstra\AstraCheckoutBundle\Promotion\PromotionProvider;
use StudioAstra\AstraCheckoutBundle\Repository\AnnouncementRepository;
use StudioAstra\AstraCheckoutBundle\Repository\PromotionRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Announcements are read from the cache (one query per hour at most, invalidated on change) and
 * filtered by date at render time: scheduled messages appear and disappear on time.
 */
final class AnnouncementProvider implements ResetInterface
{
    public const CACHE_KEY = 'astra_checkout.announcements';

    public function __construct(
        private readonly AnnouncementRepository $repository,
        private readonly PromotionRepository $promotions,
        private readonly PromotionProvider $promotionProvider,
        private readonly CacheInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<AnnouncementView> */
    public function forPlacement(AnnouncementPlacement|string $placement, ?string $locale = null): array
    {
        $placement = $placement instanceof AnnouncementPlacement ? $placement->value : $placement;
        $now = $this->clock->now()->getTimestamp();

        return array_values(array_filter($this->all(), fn (AnnouncementView $a): bool => $a->placement === $placement
            && $a->isActiveAt($now, $locale)
            && (null === $a->promotionId || $this->isPromotionActive($a->promotionId))));
    }

    /** Changes whenever what is displayed changes (used in ETags). */
    public function versionKey(?string $locale = null): string
    {
        $now = $this->clock->now()->getTimestamp();
        $keys = [];
        foreach ($this->all() as $a) {
            if ($a->isActiveAt($now, $locale) && (null === $a->promotionId || $this->isPromotionActive($a->promotionId))) {
                $keys[] = $a->versionKey;
            }
        }

        return implode(',', $keys);
    }

    /** @return list<AnnouncementView> */
    private function all(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return array_map(static fn (Announcement $a): AnnouncementView => new AnnouncementView(
                (int) $a->getId(),
                $a->getMessage(),
                $a->getLinkUrl(),
                $a->getLinkLabel(),
                $a->getPlacement()->value,
                $a->getTone(),
                $a->getStartsAt()?->getTimestamp(),
                $a->getEndsAt()?->getTimestamp(),
                $a->getLocale(),
                $a->getPromotion()?->getId(),
                $a->getPromotion()?->getCode(),
                $a->isDismissible(),
                $a->getVersionKey(),
            ), $this->repository->findEnabled());
        });
    }

    /** @var array<int, bool> */
    private array $promotionStates = [];

    public function reset(): void
    {
        $this->promotionStates = [];
    }

    private function isPromotionActive(int $id): bool
    {
        return $this->promotionStates[$id] ??= (bool) $this->promotions->find($id)?->isActiveAt($this->promotionProvider->now());
    }
}
