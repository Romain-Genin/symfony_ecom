<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInterface;
use RomainGenin\AstraCheckoutBundle\Repository\CartRepository;

/**
 * Shopping cart, persisted in the database (it survives the session and follows the customer
 * across devices once logged in). It also carries the checkout state.
 */
#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'astra_cart')]
#[ORM\Index(name: 'astra_cart_updated_idx', fields: ['updatedAt'])]
class Cart
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomerInterface::class)]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'CASCADE')]
    private ?object $customer = null;

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Embedded(class: PostalAddress::class, columnPrefix: 'shipping_')]
    private PostalAddress $shippingAddress;

    #[ORM\Column]
    private bool $billingSameAsShipping = true;

    #[ORM\Embedded(class: PostalAddress::class, columnPrefix: 'billing_')]
    private PostalAddress $billingAddress;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $shippingMethod = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customerNote = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $couponCode = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->shippingAddress = new PostalAddress();
        $this->billingAddress = new PostalAddress();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function findItem(ProductVariant $variant): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getVariant() === $variant || (null !== $variant->getId() && $item->getVariant()->getId() === $variant->getId())) {
                return $item;
            }
        }

        return null;
    }

    public function findItemById(int $id): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function addItem(CartItem $item): void
    {
        $this->items->add($item);
        $this->touch();
    }

    public function removeItem(CartItem $item): void
    {
        $this->items->removeElement($item);
        $this->touch();
    }

    public function clearItems(): void
    {
        $this->items->clear();
        $this->touch();
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }

        return $count;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function getEffectiveBillingAddress(): PostalAddress
    {
        return $this->billingSameAsShipping ? $this->shippingAddress : $this->billingAddress;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCustomer(): ?object { return $this->customer; }
    public function setCustomer(?object $c): static { $this->customer = $c; return $this; }
    /** @return Collection<int, CartItem> */
    public function getItems(): Collection { return $this->items; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): static { $this->email = null === $e ? null : mb_strtolower(trim($e)); return $this; }
    public function getShippingAddress(): PostalAddress { return $this->shippingAddress; }
    public function setShippingAddress(PostalAddress $a): static { $this->shippingAddress = $a; return $this; }
    public function isBillingSameAsShipping(): bool { return $this->billingSameAsShipping; }
    public function setBillingSameAsShipping(bool $v): static { $this->billingSameAsShipping = $v; return $this; }
    public function getBillingAddress(): PostalAddress { return $this->billingAddress; }
    public function setBillingAddress(PostalAddress $a): static { $this->billingAddress = $a; return $this; }
    public function getShippingMethod(): ?string { return $this->shippingMethod; }
    public function setShippingMethod(?string $m): static { $this->shippingMethod = $m; return $this; }
    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function setPaymentMethod(?string $m): static { $this->paymentMethod = $m; return $this; }
    public function getCustomerNote(): ?string { return $this->customerNote; }
    public function setCustomerNote(?string $n): static { $this->customerNote = $n; return $this; }
    public function getCouponCode(): ?string { return $this->couponCode; }
    public function setCouponCode(?string $c): static { $this->couponCode = null === $c || '' === trim($c) ? null : strtoupper(trim($c)); $this->touch(); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
