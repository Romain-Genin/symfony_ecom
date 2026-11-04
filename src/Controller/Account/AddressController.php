<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Controller\Account;

use Doctrine\ORM\EntityManagerInterface;
use RomainGenin\AstraCheckoutBundle\Entity\Address;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Repository\AddressRepository;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/account/addresses')]
final class AddressController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly ShopSettings $settings,
        private readonly CustomerInfo $customers,
        private readonly AddressRepository $addresses,
        private readonly \RomainGenin\AstraCheckoutBundle\Routing\ShopUrls $urls,
    ) {
    }

    #[Route('', name: 'astra_account_addresses', methods: ['GET'])]
    public function index(): Response
    {
        return $this->page('@AstraCheckout/account/addresses.html.twig', []);
    }

    #[Route('/new', name: 'astra_account_address_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        return $this->page('@AstraCheckout/account/address_form.html.twig', ['is_new' => true]);
    }

    #[Route('/{id}/edit', name: 'astra_account_address_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id): Response
    {
        $this->find($id);

        return $this->page('@AstraCheckout/account/address_form.html.twig', ['is_new' => false]);
    }

    #[Route('/{id}/delete', name: 'astra_account_address_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $address = $this->find($id);
        if (!$this->isCsrfTokenValid('delete-address-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->addresses->remove($this->customer(), $address);
        $this->em->flush();
        $this->addFlash('success', $this->translator->trans('address.deleted', [], 'AstraCheckout'));

        return $this->redirect($this->urls->path('addresses'), Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/default', name: 'astra_account_address_default', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function makeDefault(Request $request, int $id): Response
    {
        $address = $this->find($id);
        if (!$this->isCsrfTokenValid('default-address-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->addresses->makeDefault($this->customer(), $address);
        $this->em->flush();
        $this->addFlash('success', $this->translator->trans('address.default_saved', [], 'AstraCheckout'));

        return $this->redirect($this->urls->path('addresses'), Response::HTTP_SEE_OTHER);
    }

    private function find(int $id): Address
    {
        return $this->addresses->findOneForCustomer($id, $this->customer()) ?? throw $this->createNotFoundException();
    }

    /** @param array<string, mixed> $parameters */
    private function page(string $template, array $parameters, int $status = 200): Response
    {
        $response = $this->render($template, $parameters + ['account' => $this->customers->describe($this->customer())], new Response(status: $status));
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /** The logged-in customer (your user entity or the built-in Customer); login page otherwise. */
    private function customer(): object
    {
        $this->denyAccessUnlessGranted($this->settings->getCustomerRole());
        $user = $this->getUser();
        if (!$this->customers->supports($user)) {
            throw $this->createAccessDeniedException(sprintf('The logged-in user is not a %s (astra_checkout.customer.class).', $this->customers->getClass()));
        }

        return $user;
    }
}
