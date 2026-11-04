<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Controller\Account;

use Doctrine\ORM\EntityManagerInterface;
use StudioAstra\AstraCheckoutBundle\Customer\Customer;
use StudioAstra\AstraCheckoutBundle\Form\ChangePasswordType;
use StudioAstra\AstraCheckoutBundle\Form\PasswordResetRequestType;
use StudioAstra\AstraCheckoutBundle\Mailer\ShopMailer;
use StudioAstra\AstraCheckoutBundle\Repository\CustomerRepository;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PasswordResetController extends AbstractController
{
    private const SESSION_TOKEN = '_astra_reset_token';

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/account/password-reset', name: 'astra_password_reset_request', methods: ['GET', 'POST'])]
    public function request(Request $request, ShopMailer $mailer, ShopSettings $settings): Response
    {
        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer = $this->customers->findOneByEmail((string) $form->get('email')->getData());
            if (null !== $customer) {
                $token = bin2hex(random_bytes(32));
                $customer->startPasswordReset($token, new \DateTimeImmutable(sprintf('+%d seconds', $settings->getPasswordResetTtl())));
                $this->em->flush();
                $mailer->sendPasswordReset($customer, $this->generateUrl('astra_password_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL), $request->getLocale());
            }
            // Same answer whether the account exists or not: no e-mail enumeration.
            $this->addFlash('success', $this->translator->trans('account.reset_sent', [], 'AstraCheckout'));

            return $this->redirectToRoute('astra_login', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@AstraCheckout/account/reset_request.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/account/password-reset/{token}', name: 'astra_password_reset', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function fromEmail(Request $request, string $token): Response
    {
        // Moves the token out of the URL (no leak through Referer / history), PRG style.
        $request->getSession()->set(self::SESSION_TOKEN, $token);

        return $this->redirectToRoute('astra_password_reset_form');
    }

    #[Route('/account/password-reset/new', name: 'astra_password_reset_form', methods: ['GET', 'POST'], priority: 1)]
    public function reset(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $token = (string) $request->getSession()->get(self::SESSION_TOKEN, '');
        $customer = '' === $token ? null : $this->customers->findOneBy(['resetTokenHash' => hash('sha256', $token)]);
        if (!$customer instanceof Customer || !$customer->isPasswordResetTokenValid($token)) {
            $this->addFlash('error', $this->translator->trans('account.reset_invalid', [], 'AstraCheckout'));

            return $this->redirectToRoute('astra_password_reset_request');
        }

        $form = $this->createForm(ChangePasswordType::class, null, ['require_current' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setPassword($hasher->hashPassword($customer, (string) $form->get('newPassword')->getData()));
            $customer->clearPasswordReset();
            $this->em->flush();
            $request->getSession()->remove(self::SESSION_TOKEN);
            $this->addFlash('success', $this->translator->trans('account.password_changed', [], 'AstraCheckout'));

            return $this->redirectToRoute('astra_login', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@AstraCheckout/account/reset.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
