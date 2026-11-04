<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use StudioAstra\AstraCheckoutBundle\Entity\Address;
use StudioAstra\AstraCheckoutBundle\Entity\PostalAddress;

/**
 * Address book. Addresses point to the customer (your user entity or the built-in one): the
 * customer class needs no collection, so your user entity is left untouched.
 *
 * @extends ServiceEntityRepository<Address>
 */
class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Address::class);
    }

    /** @return list<Address> default first */
    public function findForCustomer(object $customer): array
    {
        return $this->findBy(['customer' => $customer], ['isDefault' => 'DESC', 'id' => 'ASC']);
    }

    public function findDefault(object $customer): ?Address
    {
        return $this->findForCustomer($customer)[0] ?? null;
    }

    public function findOneForCustomer(int $id, object $customer): ?Address
    {
        return $this->findOneBy(['id' => $id, 'customer' => $customer]);
    }

    public function add(object $customer, Address $address): void
    {
        $existing = $this->findForCustomer($customer);
        $address->setCustomer($customer);
        if ([] === $existing) {
            $address->setDefault(true);
        }
        $this->getEntityManager()->persist($address);
        if ($address->isDefault()) {
            $this->makeDefault($customer, $address, $existing);
        }
    }

    /** Saves the address unless the customer already has the same one. */
    public function addIfNew(object $customer, PostalAddress $postalAddress): void
    {
        foreach ($this->findForCustomer($customer) as $existing) {
            if ($existing->getPostalAddress()->equals($postalAddress)) {
                return;
            }
        }
        $this->add($customer, new Address($postalAddress->copy()));
    }

    public function remove(object $customer, Address $address): void
    {
        $wasDefault = $address->isDefault();
        $this->getEntityManager()->remove($address);
        if ($wasDefault) {
            foreach ($this->findForCustomer($customer) as $other) {
                if ($other !== $address) {
                    $other->setDefault(true);
                    break;
                }
            }
        }
    }

    /** @param list<Address>|null $addresses */
    public function makeDefault(object $customer, Address $address, ?array $addresses = null): void
    {
        foreach ($addresses ?? $this->findForCustomer($customer) as $other) {
            $other->setDefault($other === $address);
        }
        $address->setDefault(true);
    }
}
