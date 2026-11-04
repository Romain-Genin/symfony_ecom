<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Customer;

/**
 * Optional: implement it on your own user entity to control what the shop displays.
 * Without it, the shop reads the properties configured in astra_checkout.customer.
 */
interface CustomerInterface
{
    public function getEmail(): ?string;

    public function getFirstName(): ?string;

    public function getLastName(): ?string;

    public function getPhone(): ?string;
}
