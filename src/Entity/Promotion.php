<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use StudioAstra\AstraCheckoutBundle\Enum\PromotionType;
use StudioAstra\AstraCheckoutBundle\Repository\PromotionRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cart promotion: automatic (no code) or triggered by a coupon code.
 *
 * Scope: every product, or only products of some categories (sub-categories included) and/or
 * some products. Discounts are spread over the eligible lines, so the VAT is computed on the
 * discounted amounts.
 */
#[ORM\Entity(repositoryClass: PromotionRepository::class)]
#[ORM\Table(name: 'astra_promotion')]
#[ORM\Index(name: 'astra_promotion_active_idx', fields: ['enabled', 'code'])]
#[UniqueEntity(fields: ['code'])]
class Promotion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Internal name (back-office). */
    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private string $name;

    /** Customer-facing text: "−10 % sur votre première commande". Defaults to the name. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    /** null = automatic promotion. Stored upper-case, compared case-insensitively. */
    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Assert\Regex('/^[A-Z0-9_\-]{3,50}$/')]
    private ?string $code = null;

    #[ORM\Column(length: 20, enumType: PromotionType::class)]
    private PromotionType $type;

    /** Basis points for a percentage (1000 = 10 %), minor units for a fixed amount. */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $value;

    /** Cap of a percentage discount (minor units). */
    #[ORM\Column(nullable: true)]
    private ?int $maxDiscount = null;

    /** Minimum cart subtotal (minor units, before discounts). */
    #[ORM\Column(nullable: true)]
    private ?int $minSubtotal = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $usageLimit = null;

    #[ORM\Column(nullable: true)]
    private ?int $usageLimitPerCustomer = null;

    /** Number of placed (non-cancelled) orders that used it. */
    #[ORM\Column]
    private int $usageCount = 0;

    /** Cannot be combined with other promotions (the best offer for the customer wins). */
    #[ORM\Column]
    private bool $exclusive = false;

    /** Items already on sale (compare-at price) are not discounted again. */
    #[ORM\Column]
    private bool $excludeSaleItems = false;

    /** Automatic promotions only: show the label on the product pages concerned. */
    #[ORM\Column]
    private bool $showOnProductPage = true;

    /** Higher first when promotions stack. */
    #[ORM\Column]
    private int $priority = 0;

    #[ORM\Column]
    private bool $enabled = true;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'astra_promotion_category')]
    private Collection $categories;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'astra_promotion_product')]
    private Collection $products;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, PromotionType $type, int $value)
    {
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
        $this->categories = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function isAutomatic(): bool
    {
        return null === $this->code;
    }

    public function isActiveAt(\DateTimeImmutable $now): bool
    {
        return $this->enabled
            && (null === $this->startsAt || $this->startsAt <= $now)
            && (null === $this->endsAt || $this->endsAt > $now)
            && !$this->isUsedUp();
    }

    public function isUsedUp(): bool
    {
        return null !== $this->usageLimit && $this->usageCount >= $this->usageLimit;
    }

    public function hasScope(): bool
    {
        return !$this->categories->isEmpty() || !$this->products->isEmpty();
    }

    public function getDisplayLabel(): string
    {
        return $this->label ?: $this->name;
    }

    public function incrementUsage(): void
    {
        ++$this->usageCount;
    }

    public function decrementUsage(): void
    {
        $this->usageCount = max(0, $this->usageCount - 1);
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; $this->touch(); return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $l): static { $this->label = $l; $this->touch(); return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $c): static { $c = null === $c ? null : strtoupper(trim($c)); $this->code = '' === $c ? null : $c; $this->touch(); return $this; }
    public function getType(): PromotionType { return $this->type; }
    public function setType(PromotionType $t): static { $this->type = $t; $this->touch(); return $this; }
    public function getValue(): int { return $this->value; }
    public function setValue(int $v): static { $this->value = $v; $this->touch(); return $this; }
    public function getMaxDiscount(): ?int { return $this->maxDiscount; }
    public function setMaxDiscount(?int $v): static { $this->maxDiscount = $v; $this->touch(); return $this; }
    public function getMinSubtotal(): ?int { return $this->minSubtotal; }
    public function setMinSubtotal(?int $v): static { $this->minSubtotal = $v; $this->touch(); return $this; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $d): static { $this->startsAt = $d; $this->touch(); return $this; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $d): static { $this->endsAt = $d; $this->touch(); return $this; }
    public function getUsageLimit(): ?int { return $this->usageLimit; }
    public function setUsageLimit(?int $v): static { $this->usageLimit = $v; $this->touch(); return $this; }
    public function getUsageLimitPerCustomer(): ?int { return $this->usageLimitPerCustomer; }
    public function setUsageLimitPerCustomer(?int $v): static { $this->usageLimitPerCustomer = $v; $this->touch(); return $this; }
    public function getUsageCount(): int { return $this->usageCount; }
    public function isExclusive(): bool { return $this->exclusive; }
    public function setExclusive(bool $v): static { $this->exclusive = $v; $this->touch(); return $this; }
    public function isExcludeSaleItems(): bool { return $this->excludeSaleItems; }
    public function setExcludeSaleItems(bool $v): static { $this->excludeSaleItems = $v; $this->touch(); return $this; }
    public function isShowOnProductPage(): bool { return $this->showOnProductPage; }
    public function setShowOnProductPage(bool $v): static { $this->showOnProductPage = $v; $this->touch(); return $this; }
    public function getPriority(): int { return $this->priority; }
    public function setPriority(int $v): static { $this->priority = $v; $this->touch(); return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $v): static { $this->enabled = $v; $this->touch(); return $this; }
    /** @return Collection<int, Category> */
    public function getCategories(): Collection { return $this->categories; }
    public function addCategory(Category $c): static { if (!$this->categories->contains($c)) { $this->categories->add($c); } $this->touch(); return $this; }
    public function removeCategory(Category $c): static { $this->categories->removeElement($c); $this->touch(); return $this; }
    /** @return Collection<int, Product> */
    public function getProducts(): Collection { return $this->products; }
    public function addProduct(Product $p): static { if (!$this->products->contains($p)) { $this->products->add($p); } $this->touch(); return $this; }
    public function removeProduct(Product $p): static { $this->products->removeElement($p); $this->touch(); return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
