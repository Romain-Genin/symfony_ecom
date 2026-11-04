<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use StudioAstra\AstraCheckoutBundle\Customer\CustomerInfo;
use StudioAstra\AstraCheckoutBundle\Entity\Address;
use StudioAstra\AstraCheckoutBundle\Repository\AddressRepository;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/** The customer's addresses (edit, delete, set default): <twig:Astra:AddressBook /> */
#[AsTwigComponent('Astra:AddressBook', template: '@AstraCheckout/components/Astra/AddressBook.html.twig')]
final class AddressBook
{
    use CustomerAware;

    /** @var list<Address> */
    public array $addresses = [];
    public bool $addButton = true;
    public int $headingLevel = 2;

    public function __construct(
        private readonly AddressRepository $repository,
        private readonly Security $security,
        private readonly CustomerInfo $customers,
        private readonly ShopSettings $settings,
    ) {
    }

    #[PostMount]
    public function load(): void
    {
        $this->addresses = $this->repository->findForCustomer($this->requireCustomer($this->security, $this->customers, $this->settings));
    }
}
