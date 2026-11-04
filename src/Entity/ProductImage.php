<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'astra_product_image')]
#[ORM\Index(name: 'astra_image_product_idx', columns: ['product_id', 'position'])]
class ProductImage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    /** Path relative to the public directory (e.g. "uploads/products/shirt.jpg") or absolute URL. */
    #[ORM\Column(length: 255)]
    private string $path;

    /** Text alternative. Empty: the product name is used. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(string $path, ?string $alt = null, ?int $width = null, ?int $height = null)
    {
        $this->path = $path;
        $this->alt = $alt;
        $this->width = $width;
        $this->height = $height;
    }

    public function getAltText(): string
    {
        return null !== $this->alt && '' !== trim($this->alt) ? $this->alt : (string) $this->product?->getName();
    }

    public function isExternal(): bool
    {
        return (bool) preg_match('#^(https?:)?//#', $this->path);
    }

    public function getId(): ?int { return $this->id; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): static { $this->path = $path; return $this; }
    public function getAlt(): ?string { return $this->alt; }
    public function setAlt(?string $alt): static { $this->alt = $alt; return $this; }
    public function getWidth(): ?int { return $this->width; }
    public function setWidth(?int $w): static { $this->width = $w; return $this; }
    public function getHeight(): ?int { return $this->height; }
    public function setHeight(?int $h): static { $this->height = $h; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): static { $this->position = $p; return $this; }
}
