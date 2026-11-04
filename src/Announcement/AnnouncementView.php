<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Announcement;

/** Cache-friendly, read-only copy of an Announcement for the templates. */
final class AnnouncementView
{
    public function __construct(
        public readonly int $id,
        public readonly string $message,
        public readonly ?string $linkUrl,
        public readonly ?string $linkLabel,
        public readonly string $placement,
        public readonly string $tone,
        public readonly ?int $startsAt,
        public readonly ?int $endsAt,
        public readonly ?string $locale,
        public readonly ?int $promotionId,
        public readonly ?string $promotionCode,
        public readonly bool $dismissible,
        public readonly string $versionKey,
    ) {
    }

    public function isActiveAt(int $now, ?string $locale): bool
    {
        return (null === $this->startsAt || $this->startsAt <= $now)
            && (null === $this->endsAt || $this->endsAt > $now)
            && (null === $this->locale || null === $locale || str_starts_with($locale, $this->locale));
    }
}
