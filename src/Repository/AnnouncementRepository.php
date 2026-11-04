<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use StudioAstra\AstraCheckoutBundle\Entity\Announcement;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /** @return list<Announcement> enabled ones, with their promotion, in display order */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('p')->leftJoin('a.promotion', 'p')
            ->where('a.enabled = true')
            ->orderBy('a.position', 'ASC')->addOrderBy('a.id', 'ASC')
            ->getQuery()->getResult();
    }
}
