<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use RomainGenin\AstraCheckoutBundle\Catalog\ProductListCriteria;
use RomainGenin\AstraCheckoutBundle\Catalog\ProductPage;
use RomainGenin\AstraCheckoutBundle\Entity\Product;
use RomainGenin\AstraCheckoutBundle\Search\TextNormalizer;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Listing query in 3 cheap steps, whatever the catalog size:
     *  1. COUNT on the product table (indexed columns only),
     *  2. ids of the requested page (filters/sort on denormalized columns, no join on variants),
     *  3. hydration of those products and their images only, read-only (no change tracking).
     */
    /**
     * @param array{terms: list<list<string>>, normalized: string}|null $search prepared by DatabaseSearchEngine
     */
    public function findForListing(ProductListCriteria $criteria, bool $withPriceRange = true, ?array $search = null): ProductPage
    {
        if (null !== $criteria->search && null === $search) {
            $search = ['terms' => TextNormalizer::queryTerms($criteria->search), 'normalized' => TextNormalizer::normalize($criteria->search)];
        }
        if (null !== $search && [] === $search['terms']) {
            return new ProductPage([], 0, $criteria->page, $criteria->perPage);
        }

        $total = (int) $this->filtered($criteria, $search)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $products = [];
        if ($total > 0 && $criteria->offset() < $total) {
            $qb = $this->filtered($criteria, $search)->select('p.id')
                ->setFirstResult($criteria->offset())
                ->setMaxResults($criteria->perPage);
            $this->applySort($qb, $criteria, $search);
            $ids = array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'id'));
            $products = $this->hydrateInOrder($ids);
        }

        $priceRange = null;
        if ($withPriceRange) {
            $row = $this->filtered($criteria->withoutPriceFilters(), $search)
                ->select('MIN(p.minPrice) AS min_price', 'MAX(p.maxPrice) AS max_price')
                ->getQuery()->getSingleResult();
            if (null !== $row['min_price']) {
                $priceRange = ['min' => (int) $row['min_price'], 'max' => (int) $row['max_price']];
            }
        }

        return new ProductPage($products, $total, $criteria->page, $criteria->perPage, $priceRange);
    }

    public function findOneForDisplay(string $slug): ?Product
    {
        /** @var Product|null $product */
        $product = $this->createQueryBuilder('p')
            ->addSelect('v')
            ->leftJoin('p.variants', 'v')
            ->where('p.slug = :slug')
            ->andWhere('p.enabled = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $product) {
            // Second query instead of a second fetch-join: avoids a variants × images cartesian product.
            $this->createQueryBuilder('p')
                ->select('p', 'i', 'c')
                ->leftJoin('p.images', 'i')
                ->leftJoin('p.categories', 'c')
                ->where('p = :p')->setParameter('p', $product)
                ->getQuery()->getResult();
        }

        return $product;
    }

    /** @return list<Product> */
    public function findRelated(Product $product, int $limit = 4): array
    {
        $category = $product->getMainCategory();
        if (null === $category) {
            return [];
        }

        $ids = array_map('intval', array_column($this->createQueryBuilder('p')
            ->select('p.id')
            ->innerJoin('p.categories', 'c')
            ->where('c = :c')->andWhere('p.enabled = true')->andWhere('p != :p')->andWhere('p.inStock = true')
            ->setParameter('c', $category)->setParameter('p', $product)
            ->orderBy('p.createdAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getScalarResult(), 'id'));

        return $this->hydrateInOrder($ids);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Product>
     */
    public function hydrateInOrder(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->addSelect('i')
            ->leftJoin('p.images', 'i')
            ->where('p.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()
            ->setHint(Query::HINT_READ_ONLY, true)
            ->getResult();

        $byId = [];
        foreach ($rows as $product) {
            $byId[$product->getId()] = $product;
        }

        return array_values(array_filter(array_map(static fn (int $id): ?Product => $byId[$id] ?? null, $ids)));
    }

    /** @param array{terms: list<list<string>>, normalized: string}|null $search */
    private function filtered(ProductListCriteria $c, ?array $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')->where('p.enabled = true');

        if ([] !== $c->categoryIds) {
            $qb->andWhere($qb->expr()->in('p.id', $this->getEntityManager()->createQueryBuilder()
                ->select('pc.id')->from(Product::class, 'pc')->innerJoin('pc.categories', 'cc')
                ->where('cc.id IN (:categoryIds)')->getDQL()))
                ->setParameter('categoryIds', $c->categoryIds);
        }
        // Every word must match (any of its alternatives: stem, synonyms) in the normalized text.
        foreach ($search['terms'] ?? [] as $i => $alternatives) {
            $or = [];
            foreach ($alternatives as $j => $alternative) {
                $or[] = "p.searchText LIKE :t{$i}_{$j}";
                $qb->setParameter("t{$i}_{$j}", '%' . addcslashes($alternative, '%_') . '%');
            }
            $qb->andWhere(implode(' OR ', $or));
        }
        if (null !== $c->minPrice) {
            $qb->andWhere('p.minPrice >= :min')->setParameter('min', $c->minPrice);
        }
        if (null !== $c->maxPrice) {
            $qb->andWhere('p.minPrice <= :max')->setParameter('max', $c->maxPrice);
        }
        if ($c->inStockOnly) {
            $qb->andWhere('p.inStock = true');
        }
        if ($c->onSaleOnly) {
            $qb->andWhere('p.compareAtPrice > p.minPrice');
        }

        return $qb;
    }

    /** @param array{terms: list<list<string>>, normalized: string}|null $search */
    private function applySort(QueryBuilder $qb, ProductListCriteria $c, ?array $search = null): void
    {
        if ('relevance' === $c->sort && null !== $search) {
            // Relevance: name starting with the query, then query words found in the name, then elsewhere.
            $score = ['(CASE WHEN p.searchName LIKE :q_prefix THEN 40 ELSE 0 END)'];
            $qb->setParameter('q_prefix', ' ' . addcslashes($search['normalized'], '%_') . '%');
            foreach ($search['terms'] as $i => $alternatives) {
                $score[] = "(CASE WHEN p.searchName LIKE :t{$i}_0 THEN 10 ELSE 0 END)";
            }
            $qb->addSelect(implode(' + ', $score) . ' AS HIDDEN relevance')
                ->orderBy('relevance', 'DESC')->addOrderBy('p.inStock', 'DESC')->addOrderBy('p.createdAt', 'DESC')->addOrderBy('p.id', 'DESC');

            return;
        }

        match ($c->sort) {
            'price_asc' => $qb->orderBy('p.minPrice', 'ASC'),
            'price_desc' => $qb->orderBy('p.minPrice', 'DESC'),
            'name_asc' => $qb->orderBy('p.name', 'ASC'),
            // "relevance": in stock first, then newest. Plug a search engine for real scoring.
            'relevance' => $qb->orderBy('p.inStock', 'DESC')->addOrderBy('p.createdAt', 'DESC'),
            default => $qb->orderBy('p.createdAt', 'DESC'),
        };
        // Unique tie-breaker: stable pages (no product shown twice or skipped between pages).
        $qb->addOrderBy('p.id', 'DESC');
    }
}
