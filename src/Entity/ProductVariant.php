<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use StudioAstra\AstraCheckoutBundle\Repository\ProductVariantRepository;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[ORM\Table(name: 'astra_product_variant')]
#[ORM\Index(name: 'astra_variant_product_idx', columns: ['product_id', 'position'])]
class ProductVariant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $sku;

    /** Human label of the options, e.g. "Bleu / M" (built from the options when empty). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /** @var array<string, string> e.g. ['Couleur' => 'Bleu', 'Taille' => 'M'] */
    #[ORM\Column(type: 'json')]
    private array $options = [];

    /** Price in minor units (cents), tax included or not depending on astra_checkout.prices_include_tax. */
    #[ORM\Column]
    private int $price;

    #[ORM\Column(nullable: true)]
    private ?int $compareAtPrice = null;

    /** null = stock not tracked (always available). */
    #[ORM\Column(nullable: true)]
    private ?int $stock = null;

    #[ORM\Column]
    private int $position = 0;

    /**
     * @param array<string, string> $options
     */
    public function __construct(string $sku, int $price, ?int $stock = null, array $options = [])
    {
        $this->sku = $sku;
        $this->price = $price;
        $this->stock = $stock;
        $this->options = $options;
    }

    public function isAvailable(int $quantity = 1): bool
    {
        return null === $this->stock || $this->stock >= $quantity;
    }

    public function isLowStock(int $threshold = 5): bool
    {
        return null !== $this->stock && $this->stock > 0 && $this->stock <= $threshold;
    }

    public function decreaseStock(int $quantity): void
    {
        if (null === $this->stock) {
            return;
        }
        if ($this->stock < $quantity) {
            throw new \LogicException(sprintf('Not enough stock for "%s" (%d < %d).', $this->sku, $this->stock, $quantity));
        }
        $this->stock -= $quantity;
        $this->product?->syncIndex();
    }

    public function increaseStock(int $quantity): void
    {
        if (null === $this->stock) {
            return;
        }
        $this->stock += $quantity;
        $this->product?->syncIndex();
    }

    public function getLabel(): string
    {
        if (null !== $this->name && '' !== $this->name) {
            return $this->name;
        }

        return implode(' / ', $this->options);
    }

    public function getFullName(): string
    {
        $label = $this->getLabel();
        $name = $this->product?->getName() ?? $this->sku;

        return '' === $label ? $name : $name . ' – ' . $label;
    }

    public function getId(): ?int { return $this->id; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }
    public function getSku(): string { return $this->sku; }
    public function setSku(string $sku): static { $this->sku = $sku; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }
    /** @return array<string, string> */
    public function getOptions(): array { return $this->options; }
    /** @param array<string, string> $options */
    public function setOptions(array $options): static { $this->options = $options; $this->product?->touch(); return $this; }
    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): static { $this->price = $price; $this->product?->syncIndex(); return $this; }
    public function getCompareAtPrice(): ?int { return $this->compareAtPrice; }
    public function setCompareAtPrice(?int $p): static { $this->compareAtPrice = $p; $this->product?->syncIndex(); return $this; }
    public function getStock(): ?int { return $this->stock; }
    public function setStock(?int $stock): static { $this->stock = $stock; $this->product?->syncIndex(); return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): static { $this->position = $p; return $this; }
}
