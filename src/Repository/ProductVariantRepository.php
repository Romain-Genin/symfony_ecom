<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RomainGenin\AstraCheckoutBundle\Entity\ProductVariant;

/**
 * @extends ServiceEntityRepository<ProductVariant>
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    public function findSellable(int $id): ?ProductVariant
    {
        return $this->createQueryBuilder('v')
            ->addSelect('p')
            ->innerJoin('v.product', 'p')
            ->where('v.id = :id')->andWhere('p.enabled = true')
            ->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();
    }
}
