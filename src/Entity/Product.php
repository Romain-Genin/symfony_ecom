<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use StudioAstra\AstraCheckoutBundle\Repository\ProductRepository;

/**
 * A product is sold through its variants (size, colour…). A simple product has a single variant.
 *
 * minPrice / maxPrice / compareAtPrice / inStock are denormalized from the variants so that
 * listing pages (PLP) can filter, sort and display cards from the product table only, without
 * loading any variant.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'astra_product')]
#[ORM\Index(name: 'astra_product_listing_created_idx', fields: ['enabled', 'createdAt'])]
#[ORM\Index(name: 'astra_product_listing_price_idx', fields: ['enabled', 'minPrice'])]
#[ORM\Index(name: 'astra_product_listing_name_idx', fields: ['enabled', 'name'])]
class Product
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    /** Trusted HTML (written by the back-office), rendered as-is. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column]
    private bool $enabled = true;

    /** VAT rate in basis points (2000 = 20 %). */
    #[ORM\Column]
    private int $taxRate = 2000;

    #[ORM\Column]
    private int $minPrice = 0;

    #[ORM\Column]
    private int $maxPrice = 0;

    #[ORM\Column(nullable: true)]
    private ?int $compareAtPrice = null;

    #[ORM\Column]
    private bool $inStock = false;

    /**
     * Normalized text (lower-case, no accents) of the name, brand, SKUs, options and categories,
     * maintained by ProductSearchIndexer and used by the database search engine.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $searchText = null;

    /** Normalized name, to rank name matches first. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchName = null;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'astra_product_category')]
    private Collection $categories;

    /** @var Collection<int, ProductVariant> */
    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $variants;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->categories = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Recomputes the denormalized listing fields. Called automatically by the variants.
     */
    public function syncIndex(): void
    {
        $prices = [];
        $cheapest = null;
        $inStock = false;
        foreach ($this->variants as $variant) {
            $prices[] = $variant->getPrice();
            if (null === $cheapest || $variant->getPrice() < $cheapest->getPrice()) {
                $cheapest = $variant;
            }
            $inStock = $inStock || $variant->isAvailable();
        }

        $this->minPrice = $prices ? min($prices) : 0;
        $this->maxPrice = $prices ? max($prices) : 0;
        $this->compareAtPrice = $cheapest?->getCompareAtPrice();
        $this->inStock = $inStock;
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addVariant(ProductVariant $variant): static
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }
        $this->syncIndex();

        return $this;
    }

    public function removeVariant(ProductVariant $variant): static
    {
        $this->variants->removeElement($variant);
        $this->syncIndex();

        return $this;
    }

    public function addImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $image->setPosition($image->getPosition() ?: $this->images->count());
            $this->images->add($image);
            $image->setProduct($this);
        }
        $this->touch();

        return $this;
    }

    public function removeImage(ProductImage $image): static
    {
        $this->images->removeElement($image);
        $this->touch();

        return $this;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function getMainImage(): ?ProductImage
    {
        return $this->images->first() ?: null;
    }

    public function getMainCategory(): ?Category
    {
        return $this->categories->first() ?: null;
    }

    /** First available variant (or the first one when everything is sold out). */
    public function getDefaultVariant(): ?ProductVariant
    {
        foreach ($this->variants as $variant) {
            if ($variant->isAvailable()) {
                return $variant;
            }
        }

        return $this->variants->first() ?: null;
    }

    public function hasPriceRange(): bool
    {
        return $this->minPrice !== $this->maxPrice;
    }

    public function isOnSale(): bool
    {
        return null !== $this->compareAtPrice && $this->compareAtPrice > $this->minPrice;
    }

    /** Discount of the cheapest variant, in whole percent. */
    public function getDiscountPercent(): ?int
    {
        return $this->isOnSale() ? (int) round(100 - $this->minPrice * 100 / $this->compareAtPrice) : null;
    }

    /**
     * Distinct option names and values across variants, in variant order.
     *
     * @return array<string, list<string>> e.g. ['Couleur' => ['Bleu', 'Rouge'], 'Taille' => ['S', 'M']]
     */
    public function getOptionMatrix(): array
    {
        $matrix = [];
        foreach ($this->variants as $variant) {
            foreach ($variant->getOptions() as $name => $value) {
                $matrix[$name] ??= [];
                if (!\in_array($value, $matrix[$name], true)) {
                    $matrix[$name][] = $value;
                }
            }
        }

        return $matrix;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; $this->touch(); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getBrand(): ?string { return $this->brand; }
    public function setBrand(?string $brand): static { $this->brand = $brand; return $this; }
    public function getShortDescription(): ?string { return $this->shortDescription; }
    public function setShortDescription(?string $v): static { $this->shortDescription = $v; $this->touch(); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; $this->touch(); return $this; }
    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function setMetaTitle(?string $v): static { $this->metaTitle = $v; return $this; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $v): static { $this->metaDescription = $v; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): static { $this->enabled = $enabled; $this->touch(); return $this; }
    public function getTaxRate(): int { return $this->taxRate; }
    public function setTaxRate(int $basisPoints): static { $this->taxRate = $basisPoints; return $this; }
    public function getMinPrice(): int { return $this->minPrice; }
    public function getMaxPrice(): int { return $this->maxPrice; }
    public function getCompareAtPrice(): ?int { return $this->compareAtPrice; }
    public function isInStock(): bool { return $this->inStock; }
    public function getSearchText(): ?string { return $this->searchText; }
    public function getSearchName(): ?string { return $this->searchName; }
    /** @internal maintained by ProductSearchIndexer */
    public function setSearchIndex(?string $name, ?string $text): void { $this->searchName = $name; $this->searchText = $text; }
    /** @return Collection<int, Category> */
    public function getCategories(): Collection { return $this->categories; }
    /** @return Collection<int, ProductVariant> */
    public function getVariants(): Collection { return $this->variants; }
    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection { return $this->images; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): static { $this->createdAt = $d; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
