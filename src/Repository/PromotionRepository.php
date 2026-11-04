<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RomainGenin\AstraCheckoutBundle\Entity\Promotion;

/**
 * @extends ServiceEntityRepository<Promotion>
 */
class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    public function findOneByCode(string $code): ?Promotion
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    /**
     * Enabled automatic promotions with their scope, as plain arrays (cacheable).
     *
     * @return list<array{id: int, startsAt: ?int, endsAt: ?int, usedUp: bool, categoryIds: list<int>, productIds: list<int>, updatedAt: int}>
     */
    public function findEnabledAutomaticDescriptors(): array
    {
        $promotions = $this->createQueryBuilder('p')
            ->addSelect('c', 'pr')
            ->leftJoin('p.categories', 'c')
            ->leftJoin('p.products', 'pr')
            ->where('p.enabled = true')->andWhere('p.code IS NULL')
            ->orderBy('p.priority', 'DESC')->addOrderBy('p.id', 'ASC')
            ->getQuery()->getResult();

        return array_map(static fn (Promotion $p): array => [
            'id' => (int) $p->getId(),
            'startsAt' => $p->getStartsAt()?->getTimestamp(),
            'endsAt' => $p->getEndsAt()?->getTimestamp(),
            'usedUp' => $p->isUsedUp(),
            'categoryIds' => array_values(array_map(static fn ($c) => (int) $c->getId(), $p->getCategories()->toArray())),
            'productIds' => array_values(array_map(static fn ($pr) => (int) $pr->getId(), $p->getProducts()->toArray())),
            'updatedAt' => $p->getUpdatedAt()->getTimestamp(),
            'version' => hash('crc32b', implode('|', [$p->getDisplayLabel(), $p->getType()->value, $p->getValue(), $p->getMinSubtotal(), $p->isShowOnProductPage() ? 1 : 0, $p->getUpdatedAt()->getTimestamp()])),
        ], $promotions);
    }

    /** @param list<int> $ids @return list<Promotion> */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->addSelect('c', 'pr')->leftJoin('p.categories', 'c')->leftJoin('p.products', 'pr')
            ->where('p.id IN (:ids)')->setParameter('ids', $ids)
            ->orderBy('p.priority', 'DESC')->addOrderBy('p.id', 'ASC')
            ->getQuery()->getResult();
    }
}
