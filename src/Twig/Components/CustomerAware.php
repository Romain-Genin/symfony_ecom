<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use StudioAstra\AstraCheckoutBundle\Customer\CustomerInfo;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Account blocks require a logged-in customer: anonymous visitors are sent to your login page
 * by the firewall (the AccessDeniedException is handled like in a controller).
 */
trait CustomerAware
{
    private function requireCustomer(Security $security, CustomerInfo $customers, ShopSettings $settings): object
    {
        $user = $security->getUser();
        if (!$security->isGranted($settings->getCustomerRole()) || !$customers->supports($user)) {
            throw new AccessDeniedException('A logged-in customer is required.');
        }

        return $user;
    }
}
