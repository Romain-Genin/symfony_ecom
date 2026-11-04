<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Postal address value object, embedded in customer addresses, carts and orders
 * (orders keep an immutable copy: editing the address book never changes past orders).
 */
#[ORM\Embeddable]
class PostalAddress
{
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $street = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $street2 = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Country]
    private ?string $countryCode = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^\+?[0-9 .()\-]{6,30}$/', message: 'astra.phone.invalid')]
    private ?string $phone = null;

    public function isComplete(): bool
    {
        foreach ([$this->firstName, $this->lastName, $this->street, $this->postalCode, $this->city, $this->countryCode] as $value) {
            if (null === $value || '' === trim($value)) {
                return false;
            }
        }

        return true;
    }

    public function copy(): self
    {
        return clone $this;
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /** @return array<string, ?string> */
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'company' => $this->company,
            'street' => $this->street,
            'street2' => $this->street2,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'countryCode' => $this->countryCode,
            'phone' => $this->phone,
        ];
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    /** @return list<string> Lines ready to be displayed (country excluded, it needs translating). */
    public function getLines(): array
    {
        return array_values(array_filter([
            $this->getFullName(),
            $this->company,
            $this->street,
            $this->street2,
            trim(($this->postalCode ?? '') . ' ' . ($this->city ?? '')),
        ], static fn (?string $line): bool => null !== $line && '' !== trim($line)));
    }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $v): static { $this->firstName = self::clean($v); return $this; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $v): static { $this->lastName = self::clean($v); return $this; }
    public function getCompany(): ?string { return $this->company; }
    public function setCompany(?string $v): static { $this->company = self::clean($v); return $this; }
    public function getStreet(): ?string { return $this->street; }
    public function setStreet(?string $v): static { $this->street = self::clean($v); return $this; }
    public function getStreet2(): ?string { return $this->street2; }
    public function setStreet2(?string $v): static { $this->street2 = self::clean($v); return $this; }
    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(?string $v): static { $this->postalCode = self::clean($v); return $this; }
    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $v): static { $this->city = self::clean($v); return $this; }
    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $v): static { $this->countryCode = null === $v ? null : strtoupper(trim($v)); return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = self::clean($v); return $this; }

    private static function clean(?string $value): ?string
    {
        $value = null === $value ? null : trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return '' === $value ? null : $value;
    }
}
