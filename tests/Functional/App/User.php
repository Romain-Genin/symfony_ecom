<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Tests\Functional\App;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** The user entity of an existing website: knows nothing about the shop. */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 180, unique: true)]
        private string $email,
        #[ORM\Column]
        private string $password,
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $givenName = null,
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $familyName = null,
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getGivenName(): ?string { return $this->givenName; }
    public function getFamilyName(): ?string { return $this->familyName; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return ['ROLE_USER']; }
    public function getPassword(): string { return $this->password; }
    #[\Deprecated]
    public function eraseCredentials(): void {}
}
