<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RomainGenin\AstraCheckoutBundle\Entity\Order;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** @return list<Order> */
    public function findForCustomer(object $customer, int $page = 1, int $perPage = 10): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.customer = :c')->setParameter('c', $customer)
            ->orderBy('o.createdAt', 'DESC')->addOrderBy('o.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)
            ->getQuery()->getResult();
    }

    public function countForCustomer(object $customer): int
    {
        return (int) $this->createQueryBuilder('o')->select('COUNT(o.id)')
            ->where('o.customer = :c')->setParameter('c', $customer)
            ->getQuery()->getSingleScalarResult();
    }

    /** Orders (not cancelled) that used a coupon code, for a customer or an e-mail address. */
    public function countPromotionUses(string $code, ?object $customer, ?string $email): int
    {
        $qb = $this->createQueryBuilder('o')->select('COUNT(o.id)')
            ->where('o.couponCode = :code')->setParameter('code', $code)
            ->andWhere('o.status != :cancelled')->setParameter('cancelled', \RomainGenin\AstraCheckoutBundle\Enum\OrderStatus::Cancelled);
        $or = [];
        if (null !== $customer) {
            $or[] = 'o.customer = :customer';
            $qb->setParameter('customer', $customer);
        }
        if (null !== $email) {
            $or[] = 'o.email = :email';
            $qb->setParameter('email', mb_strtolower($email));
        }
        if ([] === $or) {
            return 0;
        }

        return (int) $qb->andWhere(implode(' OR ', $or))->getQuery()->getSingleScalarResult();
    }

    public function findOneWithItems(array $criteria): ?Order
    {
        $qb = $this->createQueryBuilder('o')->addSelect('i')->leftJoin('o.items', 'i');
        foreach ($criteria as $field => $value) {
            $qb->andWhere("o.$field = :$field")->setParameter($field, $value);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
