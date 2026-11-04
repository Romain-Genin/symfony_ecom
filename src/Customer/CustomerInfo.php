<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Customer;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Reads the customer data the shop needs (e-mail, names, phone) from any user object: the
 * built-in Customer, or your own App\Entity\User, without modifying it.
 */
final class CustomerInfo
{
    private PropertyAccessorInterface $accessor;

    /**
     * @param class-string $class
     * @param array{email: string, first_name: ?string, last_name: ?string, phone: ?string} $properties
     */
    public function __construct(
        private readonly string $class,
        private readonly array $properties,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor();
    }

    /** @return class-string */
    public function getClass(): string
    {
        return $this->class;
    }

    public function isBuiltIn(): bool
    {
        return Customer::class === $this->class;
    }

    /** Whether this (security) user is a shop customer. */
    public function supports(mixed $user): bool
    {
        return \is_object($user) && is_a($user, $this->class);
    }

    public function email(object $customer): ?string
    {
        $email = $customer instanceof CustomerInterface ? $customer->getEmail() : $this->read($customer, 'email');

        return null === $email || '' === $email ? null : mb_strtolower(trim($email));
    }

    public function firstName(object $customer): ?string
    {
        return $customer instanceof CustomerInterface ? $customer->getFirstName() : $this->read($customer, 'first_name');
    }

    public function lastName(object $customer): ?string
    {
        return $customer instanceof CustomerInterface ? $customer->getLastName() : $this->read($customer, 'last_name');
    }

    public function phone(object $customer): ?string
    {
        return $customer instanceof CustomerInterface ? $customer->getPhone() : $this->read($customer, 'phone');
    }

    public function fullName(object $customer): string
    {
        return trim(($this->firstName($customer) ?? '') . ' ' . ($this->lastName($customer) ?? ''));
    }

    /** Name to greet the customer with: first name, full name, or the part of the e-mail before "@". */
    public function displayName(object $customer): string
    {
        $name = $this->firstName($customer) ?: $this->fullName($customer);

        return '' !== $name ? $name : (strstr((string) $this->email($customer), '@', true) ?: '');
    }

    /** @return array{firstName: ?string, lastName: ?string, fullName: string, displayName: string, email: ?string, phone: ?string} */
    public function describe(object $customer): array
    {
        return [
            'firstName' => $this->firstName($customer),
            'lastName' => $this->lastName($customer),
            'fullName' => $this->fullName($customer),
            'displayName' => $this->displayName($customer),
            'email' => $this->email($customer),
            'phone' => $this->phone($customer),
        ];
    }

    private function read(object $customer, string $key): ?string
    {
        $path = $this->properties[$key] ?? null;
        if (null === $path) {
            return null;
        }
        $value = $this->accessor->getValue($customer, $path);

        return null === $value ? null : (string) $value;
    }
}
