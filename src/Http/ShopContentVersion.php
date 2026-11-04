<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Http;

use StudioAstra\AstraCheckoutBundle\Announcement\AnnouncementProvider;
use StudioAstra\AstraCheckoutBundle\Promotion\PromotionProvider;

/**
 * Part of every catalog ETag: a cached page is invalidated when a promotion or an announcement
 * starts, ends or is edited, not only when products change.
 */
final class ShopContentVersion
{
    public function __construct(
        private readonly PromotionProvider $promotions,
        private readonly AnnouncementProvider $announcements,
    ) {
    }

    public function get(?string $locale = null): string
    {
        return hash('xxh128', $this->promotions->versionKey() . '|' . $this->announcements->versionKey($locale));
    }
}
