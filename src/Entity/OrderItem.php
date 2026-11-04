<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'astra_order_item')]
class OrderItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    /** Kept for restocking and links; null once the variant is deleted from the catalog. */
    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductVariant $variant;

    #[ORM\Column(length: 255)]
    private string $productName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $variantName;

    #[ORM\Column(length: 64)]
    private string $sku;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath;

    #[ORM\Column]
    private int $unitPrice;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column]
    private int $taxRate;

    #[ORM\Column]
    private int $taxAmount;

    #[ORM\Column]
    private int $total;

    /** Share of the cart promotions allocated to this line (included in taxAmount computation). */
    #[ORM\Column]
    private int $discountAmount = 0;

    public function __construct(Order $order, ProductVariant $variant, int $quantity, int $taxAmount, int $discountAmount = 0)
    {
        $product = $variant->getProduct();
        $this->order = $order;
        $this->variant = $variant;
        $this->productName = $product->getName();
        $this->variantName = '' === $variant->getLabel() ? null : $variant->getLabel();
        $this->sku = $variant->getSku();
        $this->imagePath = $product->getMainImage()?->getPath();
        $this->unitPrice = $variant->getPrice();
        $this->quantity = $quantity;
        $this->taxRate = $product->getTaxRate();
        $this->taxAmount = $taxAmount;
        $this->total = $this->unitPrice * $quantity;
        $this->discountAmount = $discountAmount;
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function getVariant(): ?ProductVariant { return $this->variant; }
    public function getProductName(): string { return $this->productName; }
    public function getVariantName(): ?string { return $this->variantName; }
    public function getSku(): string { return $this->sku; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function getUnitPrice(): int { return $this->unitPrice; }
    public function getQuantity(): int { return $this->quantity; }
    public function getTaxRate(): int { return $this->taxRate; }
    public function getTaxAmount(): int { return $this->taxAmount; }
    public function getTotal(): int { return $this->total; }
    public function getDiscountAmount(): int { return $this->discountAmount; }
}
