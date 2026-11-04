<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Controller\Account;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    /** The POST is handled by the "form_login" authenticator of your firewall (see README). */
    #[Route('/account/login', name: 'astra_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('astra_account');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $response = $this->render('@AstraCheckout/account/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $error,
            'from_checkout' => $request->query->getBoolean('checkout'),
        ], new Response(status: null !== $error ? 422 : 200));
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    #[Route('/account/logout', name: 'astra_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the "logout" key of your firewall (see README).');
    }
}
