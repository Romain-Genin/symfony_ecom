<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RomainGenin\AstraCheckoutBundle\Customer\Customer;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function findOneByEmail(string $email): ?Customer
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Customer) {
            return;
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
