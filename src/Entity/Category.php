<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use StudioAstra\AstraCheckoutBundle\Repository\CategoryRepository;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'astra_category')]
#[ORM\Index(name: 'astra_category_parent_idx', columns: ['parent_id', 'position'])]
class Category
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 180, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Category $parent = null;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $enabled = true;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->children = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /** @return list<Category> From the root down to the direct parent. */
    public function getAncestors(): array
    {
        $ancestors = [];
        $seen = [];
        for ($current = $this->parent; null !== $current && !isset($seen[spl_object_id($current)]); $current = $current->parent) {
            $seen[spl_object_id($current)] = true;
            array_unshift($ancestors, $current);
        }

        return $ancestors;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $d): static { $this->metaDescription = $d; return $this; }
    public function getParent(): ?Category { return $this->parent; }
    public function setParent(?Category $parent): static
    {
        if ($parent === $this) {
            throw new \InvalidArgumentException('A category cannot be its own parent.');
        }
        $this->parent = $parent;

        return $this;
    }
    /** @return Collection<int, Category> */
    public function getChildren(): Collection { return $this->children; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): static { $this->position = $p; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $e): static { $this->enabled = $e; return $this; }
}
