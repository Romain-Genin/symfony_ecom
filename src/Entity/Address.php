<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInterface;
use RomainGenin\AstraCheckoutBundle\Repository\AddressRepository;
use Symfony\Component\Validator\Constraints as Assert;

/** Entry of a customer's address book. */
#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ORM\Table(name: 'astra_address')]
class Address
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Your user entity (astra_checkout.customer.class) or the built-in Customer. */
    #[ORM\ManyToOne(targetEntity: CustomerInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?object $customer = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $label = null;

    #[ORM\Embedded(class: PostalAddress::class, columnPrefix: false)]
    #[Assert\Valid]
    private PostalAddress $postalAddress;

    #[ORM\Column]
    private bool $isDefault = false;

    public function __construct(?PostalAddress $postalAddress = null)
    {
        $this->postalAddress = $postalAddress ?? new PostalAddress();
    }

    public function getId(): ?int { return $this->id; }
    public function getCustomer(): ?object { return $this->customer; }
    public function setCustomer(?object $c): static { $this->customer = $c; return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $l): static { $this->label = $l; return $this; }
    public function getPostalAddress(): PostalAddress { return $this->postalAddress; }
    public function setPostalAddress(PostalAddress $a): static { $this->postalAddress = $a; return $this; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setDefault(bool $d): static { $this->isDefault = $d; return $this; }
}
