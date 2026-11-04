<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RomainGenin\AstraCheckoutBundle\Entity\Category;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findOneEnabledBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug, 'enabled' => true]);
    }

    /**
     * Whole enabled tree as plain arrays, in one query (cacheable, no entity hydration).
     *
     * @return list<array{id: int, name: string, slug: string, children: list<array<string, mixed>>}>
     */
    public function getTree(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id', 'c.name', 'c.slug', 'IDENTITY(c.parent) AS parent')
            ->where('c.enabled = true')
            ->orderBy('c.position', 'ASC')->addOrderBy('c.name', 'ASC')
            ->getQuery()->getArrayResult();

        $nodes = [];
        foreach ($rows as $row) {
            $nodes[$row['id']] = ['id' => (int) $row['id'], 'name' => $row['name'], 'slug' => $row['slug'], 'parent' => $row['parent'] ? (int) $row['parent'] : null, 'children' => []];
        }
        $build = function (?int $parent) use (&$build, $nodes): array {
            $out = [];
            foreach ($nodes as $node) {
                if ($node['parent'] === $parent) {
                    $node['children'] = $build($node['id']);
                    unset($node['parent']);
                    $out[] = $node;
                }
            }

            return $out;
        };

        return $build(null);
    }

    /** @return list<int> the category id and all its enabled descendants */
    public function getDescendantIds(Category $category): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id', 'IDENTITY(c.parent) AS parent')
            ->where('c.enabled = true')
            ->getQuery()->getArrayResult();
        $children = [];
        foreach ($rows as $row) {
            if ($row['parent']) {
                $children[(int) $row['parent']][] = (int) $row['id'];
            }
        }
        $ids = [];
        $queue = [(int) $category->getId()];
        while ($queue) {
            $id = array_shift($queue);
            if (isset($ids[$id])) {
                continue;
            }
            $ids[$id] = $id;
            array_push($queue, ...($children[$id] ?? []));
        }

        return array_values($ids);
    }
}
