<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Entity\Address;
use RomainGenin\AstraCheckoutBundle\Form\AddressType;
use RomainGenin\AstraCheckoutBundle\Http\PageResponse;
use RomainGenin\AstraCheckoutBundle\Repository\AddressRepository;
use RomainGenin\AstraCheckoutBundle\Routing\ShopUrls;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Add / edit an address on YOUR page (route accepting POST; {id} parameter to edit):
 * <twig:Astra:AddressForm />. Redirects to the address book once saved.
 */
#[AsTwigComponent('Astra:AddressForm', template: '@AstraCheckout/components/Astra/AddressForm.html.twig')]
final class AddressForm
{
    use CustomerAware;

    public ?int $id = null;
    public ?FormView $form = null;
    public bool $isNew = true;

    public function __construct(
        private readonly AddressRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly FormFactoryInterface $forms,
        private readonly Security $security,
        private readonly CustomerInfo $customers,
        private readonly ShopSettings $settings,
        private readonly RequestStack $requestStack,
        private readonly PageResponse $page,
        private readonly ShopUrls $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[PostMount]
    public function handle(): void
    {
        $customer = $this->requireCustomer($this->security, $this->customers, $this->settings);
        $request = $this->requestStack->getCurrentRequest() ?? new Request();
        $id = $this->id ?? $request->attributes->get('id');
        if (null !== $id) {
            $address = $this->repository->findOneForCustomer((int) $id, $customer);
            if (null === $address) {
                $this->page->setStatus(404);

                return;
            }
            $this->isNew = false;
        } else {
            $address = new Address();
            $address->getPostalAddress()->setFirstName($this->customers->firstName($customer))->setLastName($this->customers->lastName($customer))->setPhone($this->customers->phone($customer));
            if ([] !== $this->settings->getCountries()) {
                $address->getPostalAddress()->setCountryCode($this->settings->getCountries()[0]);
            }
        }

        $form = $this->forms->create(AddressType::class, $address);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isNew) {
                $this->repository->add($customer, $address);
            } elseif ($address->isDefault()) {
                $this->repository->makeDefault($customer, $address);
            }
            $this->em->flush();
            if ($request->hasSession()) {
                $request->getSession()->getFlashBag()->add('success', $this->translator->trans('address.saved', [], 'AstraCheckout'));
            }
            $this->page->redirect($this->urls->path('addresses'));

            return;
        }
        if ($form->isSubmitted()) {
            $this->page->setStatus(422);
            if (!$this->isNew) {
                $this->em->refresh($address);
            }
        }
        $this->form = $form->createView();
    }
}
