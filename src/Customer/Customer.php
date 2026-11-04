<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Customer;

use Doctrine\ORM\Mapping as ORM;
use RomainGenin\AstraCheckoutBundle\Repository\CustomerRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Built-in customer account, used when your application has no user entity of its own.
 * To reuse your existing users instead, set astra_checkout.customer.class (see README).
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'astra_customer')]
#[UniqueEntity(fields: ['email'], message: 'astra.email.taken')]
class Customer implements CustomerInterface, UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $lastName = '';

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $phone = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resetTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER', 'ROLE_CUSTOMER']));
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /** Stores a hash of the reset token: a database leak does not leak usable tokens. */
    public function startPasswordReset(string $plainToken, \DateTimeImmutable $expiresAt): void
    {
        $this->resetTokenHash = hash('sha256', $plainToken);
        $this->resetTokenExpiresAt = $expiresAt;
    }

    public function isPasswordResetTokenValid(string $plainToken): bool
    {
        return null !== $this->resetTokenHash
            && null !== $this->resetTokenExpiresAt
            && $this->resetTokenExpiresAt > new \DateTimeImmutable()
            && hash_equals($this->resetTokenHash, hash('sha256', $plainToken));
    }

    public function clearPasswordReset(): void
    {
        $this->resetTokenHash = null;
        $this->resetTokenExpiresAt = null;
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = mb_strtolower(trim($email)); return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hash): static { $this->password = $hash; return $this; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(?string $v): static { $this->firstName = trim((string) $v); return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(?string $v): static { $this->lastName = trim((string) $v); return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }
    /** @param list<string> $roles */
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
