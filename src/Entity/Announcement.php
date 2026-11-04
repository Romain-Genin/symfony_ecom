<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use StudioAstra\AstraCheckoutBundle\Enum\AnnouncementPlacement;
use StudioAstra\AstraCheckoutBundle\Repository\AnnouncementRepository;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Scheduled message (free shipping, sales, opening hours…). Linked to a promotion, it is only
 * shown while that promotion is active.
 */
#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
#[ORM\Table(name: 'astra_announcement')]
class Announcement
{
    public const TONES = ['neutral', 'promo', 'info', 'warning'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $message;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkUrl = null;

    /** Text of the link (must make sense out of context, WCAG 2.4.4). */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $linkLabel = null;

    #[ORM\Column(length: 20, enumType: AnnouncementPlacement::class)]
    private AnnouncementPlacement $placement;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::TONES)]
    private string $tone = 'neutral';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /** null = every locale. */
    #[ORM\Column(length: 12, nullable: true)]
    private ?string $locale = null;

    #[ORM\ManyToOne(targetEntity: Promotion::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Promotion $promotion = null;

    /** Visitors can hide it (remembered in their browser). */
    #[ORM\Column]
    private bool $dismissible = false;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $message, AnnouncementPlacement $placement = AnnouncementPlacement::TopBar)
    {
        $this->message = $message;
        $this->placement = $placement;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isActiveAt(\DateTimeImmutable $now, ?string $locale = null): bool
    {
        return $this->enabled
            && (null === $this->startsAt || $this->startsAt <= $now)
            && (null === $this->endsAt || $this->endsAt > $now)
            && (null === $this->locale || null === $locale || str_starts_with($locale, $this->locale))
            && (null === $this->promotion || $this->promotion->isActiveAt($now));
    }

    /** Changes when the content changes, so a dismissed announcement re-appears once edited. */
    public function getVersionKey(): string
    {
        return $this->id . '-' . hash('crc32b', implode('|', [$this->message, $this->linkUrl, $this->linkLabel, $this->tone, $this->updatedAt->getTimestamp()]));
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $m): static { $this->message = $m; $this->touch(); return $this; }
    public function getLinkUrl(): ?string { return $this->linkUrl; }
    public function setLinkUrl(?string $u): static { $this->linkUrl = $u; $this->touch(); return $this; }
    public function getLinkLabel(): ?string { return $this->linkLabel; }
    public function setLinkLabel(?string $l): static { $this->linkLabel = $l; $this->touch(); return $this; }
    public function getPlacement(): AnnouncementPlacement { return $this->placement; }
    public function setPlacement(AnnouncementPlacement $p): static { $this->placement = $p; $this->touch(); return $this; }
    public function getTone(): string { return $this->tone; }
    public function setTone(string $t): static { $this->tone = $t; $this->touch(); return $this; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $d): static { $this->startsAt = $d; $this->touch(); return $this; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $d): static { $this->endsAt = $d; $this->touch(); return $this; }
    public function getLocale(): ?string { return $this->locale; }
    public function setLocale(?string $l): static { $this->locale = $l; $this->touch(); return $this; }
    public function getPromotion(): ?Promotion { return $this->promotion; }
    public function setPromotion(?Promotion $p): static { $this->promotion = $p; $this->touch(); return $this; }
    public function isDismissible(): bool { return $this->dismissible; }
    public function setDismissible(bool $d): static { $this->dismissible = $d; $this->touch(); return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): static { $this->position = $p; $this->touch(); return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $e): static { $this->enabled = $e; $this->touch(); return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
