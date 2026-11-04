<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'astra_cart_item')]
#[ORM\UniqueConstraint(name: 'astra_cart_item_variant_uniq', columns: ['cart_id', 'variant_id'])]
class CartItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Cart $cart;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductVariant $variant;

    #[ORM\Column]
    private int $quantity;

    public function __construct(Cart $cart, ProductVariant $variant, int $quantity)
    {
        $this->cart = $cart;
        $this->variant = $variant;
        $this->quantity = $quantity;
    }

    public function getId(): ?int { return $this->id; }
    public function getCart(): Cart { return $this->cart; }
    public function getVariant(): ProductVariant { return $this->variant; }
    public function getProduct(): Product { return $this->variant->getProduct(); }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $q): static { $this->quantity = $q; $this->cart->touch(); return $this; }
    public function getUnitPrice(): int { return $this->variant->getPrice(); }
    public function getTotal(): int { return $this->variant->getPrice() * $this->quantity; }
}
