<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use StudioAstra\AstraCheckoutBundle\Entity\Cart;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    /** Cart with items, variants, products and images in a single query. */
    public function findWithItems(int $id): ?Cart
    {
        return $this->withItems()->where('c.id = :id')->setParameter('id', $id)->getQuery()->getOneOrNullResult();
    }

    public function findForCustomer(object $customer): ?Cart
    {
        return $this->withItems()->where('c.customer = :customer')->setParameter('customer', $customer)->getQuery()->getOneOrNullResult();
    }

    /** Deletes anonymous carts not updated since $before. */
    public function purgeAnonymous(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('c')->delete()
            ->where('c.customer IS NULL')->andWhere('c.updatedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()->execute();
    }

    private function withItems(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('i', 'v', 'p', 'img')
            ->leftJoin('c.items', 'i')
            ->leftJoin('i.variant', 'v')
            ->leftJoin('v.product', 'p')
            ->leftJoin('p.images', 'img');
    }
}
