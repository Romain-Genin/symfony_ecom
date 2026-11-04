<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Controller\Account;

use Doctrine\ORM\EntityManagerInterface;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Entity\Address;
use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Repository\AddressRepository;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;
use RomainGenin\AstraCheckoutBundle\Form\ChangePasswordType;
use RomainGenin\AstraCheckoutBundle\Form\DeleteAccountType;
use RomainGenin\AstraCheckoutBundle\Form\ProfileType;
use RomainGenin\AstraCheckoutBundle\Order\OrderStateMachine;
use RomainGenin\AstraCheckoutBundle\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/account')]
final class AccountController extends AbstractController
{
    private const ORDERS_PER_PAGE = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orders,
        private readonly TranslatorInterface $translator,
        private readonly ShopSettings $settings,
        private readonly CustomerInfo $customers,
        private readonly AddressRepository $addresses,
        private readonly \RomainGenin\AstraCheckoutBundle\Routing\ShopUrls $urls,
    ) {
    }

    #[Route('', name: 'astra_account', methods: ['GET'])]
    public function dashboard(): Response
    {
        $customer = $this->customer();

        return $this->page('@AstraCheckout/account/dashboard.html.twig', [
            'order_count' => $this->orders->countForCustomer($customer),
            'default_address' => $this->addresses->findDefault($customer),
        ]);
    }

    #[Route('/profile', name: 'astra_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        $customer = $this->customer();
        $form = $this->createForm(ProfileType::class, $customer);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('account.profile_saved', [], 'AstraCheckout'));

            return $this->redirectToRoute('astra_account_profile', [], Response::HTTP_SEE_OTHER);
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($customer);
        }

        return $this->page('@AstraCheckout/account/profile.html.twig', ['form' => $form], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/password', name: 'astra_account_password', methods: ['GET', 'POST'])]
    public function password(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $customer = $this->customer();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setPassword($hasher->hashPassword($customer, (string) $form->get('newPassword')->getData()));
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('account.password_changed', [], 'AstraCheckout'));

            return $this->redirect($this->urls->path('account'), Response::HTTP_SEE_OTHER);
        }

        return $this->page('@AstraCheckout/account/password.html.twig', ['form' => $form], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/orders', name: 'astra_account_orders', methods: ['GET'])]
    public function orders(): Response
    {
        return $this->page('@AstraCheckout/account/orders.html.twig', []);
    }

    #[Route('/orders/{number}', name: 'astra_account_order', methods: ['GET'])]
    public function order(string $number): Response
    {
        return $this->page('@AstraCheckout/account/order.html.twig', ['number' => $number]);
    }

    #[Route('/orders/{number}/cancel', name: 'astra_account_order_cancel', methods: ['POST'])]
    public function cancelOrder(Request $request, string $number, OrderStateMachine $stateMachine): Response
    {
        $order = $this->orders->findOneWithItems(['number' => $number, 'customer' => $this->customer()]) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('cancel-order-' . $order->getNumber(), (string) $request->request->get('_token')) || !$order->isCancellableByCustomer()) {
            throw $this->createAccessDeniedException();
        }
        $stateMachine->apply($order, OrderStatus::Cancelled);
        $this->addFlash('success', $this->translator->trans('account.order_cancelled', ['%number%' => $order->getNumber()], 'AstraCheckout'));

        return $this->redirect($this->urls->path('order', ['number' => $order->getNumber()]), Response::HTTP_SEE_OTHER);
    }

    /** GDPR right to erasure: orders are kept (legal obligation) but detached from the account. */
    #[Route('/delete', name: 'astra_account_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, Security $security): Response
    {
        $customer = $this->customer();
        $form = $this->createForm(DeleteAccountType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->createQuery('UPDATE ' . Order::class . ' o SET o.customer = NULL WHERE o.customer = :c')->setParameter('c', $customer)->execute();
            $this->em->createQuery('DELETE FROM ' . Cart::class . ' c WHERE c.customer = :c')->setParameter('c', $customer)->execute();
            $this->em->createQuery('DELETE FROM ' . Address::class . ' a WHERE a.customer = :c')->setParameter('c', $customer)->execute();
            $this->em->remove($customer);
            $this->em->flush();
            try {
                $security->logout(false);
            } catch (\Throwable) {
                $request->getSession()->invalidate();
            }
            $request->getSession()->getFlashBag()->add('success', $this->translator->trans('account.deleted', [], 'AstraCheckout'));

            return $this->redirect($this->urls->path('catalog'), Response::HTTP_SEE_OTHER);
        }

        return $this->page('@AstraCheckout/account/delete.html.twig', ['form' => $form], $form->isSubmitted() ? 422 : 200);
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
