<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInterface;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;
use RomainGenin\AstraCheckoutBundle\Repository\OrderRepository;

/**
 * A placed order: an immutable snapshot of the cart (names, prices, addresses), so that later
 * catalog changes never alter it.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'astra_order')]
#[ORM\Index(name: 'astra_order_customer_idx', columns: ['customer_id', 'created_at'])]
#[ORM\Index(name: 'astra_order_status_idx', fields: ['status', 'createdAt'])]
class Order
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $number;

    /** Secret used to show the confirmation page to guests. */
    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: CustomerInterface::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?object $customer = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 30, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PendingPayment;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column]
    private int $subtotal = 0;

    #[ORM\Column]
    private int $shippingTotal = 0;

    #[ORM\Column]
    private int $discountTotal = 0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $couponCode = null;

    /** @var list<array{id: int, name: string, label: string, code: ?string, amount: int, freeShipping: bool}> */
    #[ORM\Column(type: 'json')]
    private array $promotions = [];

    #[ORM\Column]
    private int $taxTotal = 0;

    #[ORM\Column]
    private int $total = 0;

    #[ORM\Column]
    private bool $pricesIncludeTax = true;

    #[ORM\Column(length: 50)]
    private string $shippingMethodCode;

    #[ORM\Column(length: 150)]
    private string $shippingMethodLabel;

    #[ORM\Column(length: 50)]
    private string $paymentMethodCode;

    #[ORM\Column(length: 150)]
    private string $paymentMethodLabel;

    #[ORM\Embedded(class: PostalAddress::class, columnPrefix: 'shipping_')]
    private PostalAddress $shippingAddress;

    #[ORM\Embedded(class: PostalAddress::class, columnPrefix: 'billing_')]
    private PostalAddress $billingAddress;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customerNote = null;

    #[ORM\Column(length: 12)]
    private string $locale = 'fr';

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    /** @var list<array{status: string, at: string}> */
    #[ORM\Column(type: 'json')]
    private array $history = [];

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct(string $number, string $email, string $currency)
    {
        $this->number = $number;
        $this->email = $email;
        $this->currency = $currency;
        $this->token = bin2hex(random_bytes(24));
        $this->items = new ArrayCollection();
        $this->shippingAddress = new PostalAddress();
        $this->billingAddress = new PostalAddress();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->recordStatus();
    }

    public function addItem(OrderItem $item): void
    {
        $this->items->add($item);
    }

    /** Use OrderStateMachine::apply(): it validates the transition, restocks and dispatches events. */
    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        if (OrderStatus::Paid === $status && null === $this->paidAt) {
            $this->paidAt = $this->updatedAt;
        }
        $this->recordStatus();
    }

    public function getItemCount(): int
    {
        return array_sum(array_map(static fn (OrderItem $i): int => $i->getQuantity(), $this->items->toArray()));
    }

    public function isCancellableByCustomer(): bool
    {
        return OrderStatus::PendingPayment === $this->status;
    }

    private function recordStatus(): void
    {
        $this->history[] = ['status' => $this->status->value, 'at' => (new \DateTimeImmutable())->format(\DATE_ATOM)];
    }

    /** @param array{subtotal: int, discount?: int, shipping: int, tax: int, total: int} $totals */
    public function setTotals(array $totals, bool $pricesIncludeTax): void
    {
        $this->subtotal = $totals['subtotal'];
        $this->discountTotal = $totals['discount'] ?? 0;
        $this->shippingTotal = $totals['shipping'];
        $this->taxTotal = $totals['tax'];
        $this->total = $totals['total'];
        $this->pricesIncludeTax = $pricesIncludeTax;
    }

    public function setShippingMethod(string $code, string $label): void
    {
        $this->shippingMethodCode = $code;
        $this->shippingMethodLabel = $label;
    }

    public function setPaymentMethod(string $code, string $label): void
    {
        $this->paymentMethodCode = $code;
        $this->paymentMethodLabel = $label;
    }

    public function getId(): ?int { return $this->id; }
    public function getNumber(): string { return $this->number; }
    public function getToken(): string { return $this->token; }
    public function getCustomer(): ?object { return $this->customer; }
    public function setCustomer(?object $c): static { $this->customer = $c; return $this; }
    public function getEmail(): string { return $this->email; }
    public function getStatus(): OrderStatus { return $this->status; }
    public function getCurrency(): string { return $this->currency; }
    public function getSubtotal(): int { return $this->subtotal; }
    public function getShippingTotal(): int { return $this->shippingTotal; }
    public function getTaxTotal(): int { return $this->taxTotal; }
    public function getDiscountTotal(): int { return $this->discountTotal; }
    public function getCouponCode(): ?string { return $this->couponCode; }
    public function setCouponCode(?string $c): static { $this->couponCode = $c; return $this; }
    /** @return list<array{id: int, name: string, label: string, code: ?string, amount: int, freeShipping: bool}> */
    public function getPromotions(): array { return $this->promotions; }
    /** @param list<array{id: int, name: string, label: string, code: ?string, amount: int, freeShipping: bool}> $p */
    public function setPromotions(array $p): static { $this->promotions = $p; return $this; }
    public function getTotal(): int { return $this->total; }
    public function isPricesIncludeTax(): bool { return $this->pricesIncludeTax; }
    public function getShippingMethodCode(): string { return $this->shippingMethodCode; }
    public function getShippingMethodLabel(): string { return $this->shippingMethodLabel; }
    public function getPaymentMethodCode(): string { return $this->paymentMethodCode; }
    public function getPaymentMethodLabel(): string { return $this->paymentMethodLabel; }
    public function getShippingAddress(): PostalAddress { return $this->shippingAddress; }
    public function setShippingAddress(PostalAddress $a): static { $this->shippingAddress = $a->copy(); return $this; }
    public function getBillingAddress(): PostalAddress { return $this->billingAddress; }
    public function setBillingAddress(PostalAddress $a): static { $this->billingAddress = $a->copy(); return $this; }
    public function getCustomerNote(): ?string { return $this->customerNote; }
    public function setCustomerNote(?string $n): static { $this->customerNote = $n; return $this; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $l): static { $this->locale = $l; return $this; }
    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection { return $this->items; }
    /** @return list<array{status: string, at: string}> */
    public function getHistory(): array { return $this->history; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
}
