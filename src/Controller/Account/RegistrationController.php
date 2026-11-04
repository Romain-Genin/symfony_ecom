<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Controller\Account;

use Doctrine\ORM\EntityManagerInterface;
use StudioAstra\AstraCheckoutBundle\Cart\CartContext;
use StudioAstra\AstraCheckoutBundle\Cart\CartManager;
use StudioAstra\AstraCheckoutBundle\Customer\Customer;
use StudioAstra\AstraCheckoutBundle\Form\RegistrationType;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/account/register', name: 'astra_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        Security $security,
        ShopSettings $settings,
        CartContext $cartContext,
        CartManager $cartManager,
        TranslatorInterface $translator,
    ): Response {
        if (!$settings->isRegistrationEnabled()) {
            throw $this->createNotFoundException();
        }
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('astra_account');
        }

        $customer = new Customer();
        $form = $this->createForm(RegistrationType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setPassword($hasher->hashPassword($customer, (string) $form->get('plainPassword')->getData()));
            $em->persist($customer);
            $em->flush();

            $this->addFlash('success', $translator->trans('account.registered', ['%name%' => $customer->getFirstName()], 'AstraCheckout'));
            try {
                $cartContext->mergeAnonymousCartInto($customer, $cartManager);
                $response = $security->login($customer);
            } catch (\Throwable) {
                // Several authenticators on the firewall: let the customer log in.
                return $this->redirectToRoute('astra_login', [], Response::HTTP_SEE_OTHER);
            }

            return $response ?? $this->redirect($this->container->get('router')->generate('astra_account'), Response::HTTP_SEE_OTHER);
        }

        $response = $this->render('@AstraCheckout/account/register.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
