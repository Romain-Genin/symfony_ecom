<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use RomainGenin\AstraCheckoutBundle\Cart\CartContext;
use RomainGenin\AstraCheckoutBundle\Cart\CartManager;
use RomainGenin\AstraCheckoutBundle\Checkout\CheckoutFlow;
use RomainGenin\AstraCheckoutBundle\Checkout\CheckoutProcessor;
use RomainGenin\AstraCheckoutBundle\Customer\CustomerInfo;
use RomainGenin\AstraCheckoutBundle\Entity\Address;
use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Exception\CartException;
use RomainGenin\AstraCheckoutBundle\Form\CheckoutAddressType;
use RomainGenin\AstraCheckoutBundle\Form\PlaceOrderType;
use RomainGenin\AstraCheckoutBundle\Form\ShippingPaymentType;
use RomainGenin\AstraCheckoutBundle\Http\PageResponse;
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodRegistry;
use RomainGenin\AstraCheckoutBundle\Pricing\Totals;
use RomainGenin\AstraCheckoutBundle\Pricing\TotalsCalculator;
use RomainGenin\AstraCheckoutBundle\Repository\AddressRepository;
use RomainGenin\AstraCheckoutBundle\Routing\ShopUrls;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodRegistry;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * The whole checkout in one block, on YOUR page: <twig:Astra:Checkout />.
 *
 * Steps (address → shipping & payment → review) are selected with ?step= (or the "step" prop);
 * forms post to your page itself, so its route must accept POST. Redirections and 422 answers
 * are applied to your response automatically (PageResponse).
 */
#[AsTwigComponent('Astra:Checkout', template: '@AstraCheckout/components/Astra/Checkout.html.twig')]
final class Checkout
{
    public ?string $step = null;
    public bool $summary = true;

    public ?Cart $cart = null;
    public ?FormView $form = null;
    public ?Totals $totals = null;
    /** @var array<string, mixed> step-specific data (saved addresses, methods, costs…) */
    public array $data = [];

    public function __construct(
        private readonly CartContext $context,
        private readonly CartManager $cartManager,
        private readonly CheckoutFlow $flow,
        private readonly CheckoutProcessor $processor,
        private readonly TotalsCalculator $calculator,
        private readonly ShippingMethodRegistry $shipping,
        private readonly PaymentMethodRegistry $payment,
        private readonly EntityManagerInterface $em,
        private readonly ShopSettings $settings,
        private readonly TranslatorInterface $translator,
        private readonly CustomerInfo $customers,
        private readonly AddressRepository $addresses,
        private readonly Security $security,
        private readonly FormFactoryInterface $forms,
        private readonly RequestStack $requestStack,
        private readonly PageResponse $page,
        private readonly ShopUrls $urls,
    ) {
    }

    #[PostMount]
    public function handle(): void
    {
        $request = $this->requestStack->getCurrentRequest() ?? new Request();
        $cart = $this->context->getCart();
        if (null === $cart || $cart->isEmpty()) {
            $this->page->redirect($this->urls->path('cart'));

            return;
        }
        // Stock or availability changed: the customer reviews the updated cart first.
        $notices = $this->cartManager->revalidate($cart);
        if ([] !== $notices || $cart->isEmpty()) {
            foreach ($notices as $notice) {
                $this->flash($request, 'warning', $this->translator->trans($notice['key'], $notice['parameters'], 'AstraCheckout'));
            }
            $this->page->redirect($this->urls->path('cart'));

            return;
        }

        $this->cart = $cart;
        $step = $this->step ?? (string) $request->query->get('step', '');
        if (!\in_array($step, CheckoutFlow::STEPS, true)) {
            $step = $this->flow->firstIncompleteStep($cart);
        } elseif ('address' !== $step && !$this->flow->canAccess($cart, $step)) {
            $this->page->redirect($this->urls->path('checkout', ['step' => $this->flow->firstIncompleteStep($cart)]));

            return;
        }
        $this->step = $step;

        match ($step) {
            'address' => $this->address($request, $cart),
            'shipping' => $this->shippingAndPayment($request, $cart),
            default => $this->review($request, $cart),
        };
        $this->totals ??= $this->calculator->calculate($cart);
    }

    /** @return list<string> */
    public function getSteps(): array
    {
        return CheckoutFlow::STEPS;
    }

    private function address(Request $request, Cart $cart): void
    {
        $customer = $this->context->getCustomer();
        if (null === $customer && !$this->settings->isGuestCheckoutAllowed()) {
            $firewall = $this->security->getFirewallConfig($request)?->getName() ?? 'main';
            $request->getSession()->set('_security.' . $firewall . '.target_path', $this->urls->path('checkout', ['step' => 'address']));
            $this->page->redirect($this->urls->path('login', ['checkout' => 1]));

            return;
        }

        $saved = null === $customer ? [] : $this->addresses->findForCustomer($customer);
        if (null !== $customer) {
            $cart->setEmail($this->customers->email($customer));
            if (!$cart->getShippingAddress()->isComplete() && [] !== $saved) {
                $cart->setShippingAddress($saved[0]->getPostalAddress()->copy());
            }
            if (null === $cart->getShippingAddress()->getFirstName()) {
                $cart->getShippingAddress()->setFirstName($this->customers->firstName($customer))->setLastName($this->customers->lastName($customer))->setPhone($this->customers->phone($customer));
            }
        }
        if (null === $cart->getShippingAddress()->getCountryCode() && [] !== $this->settings->getCountries()) {
            $cart->getShippingAddress()->setCountryCode($this->settings->getCountries()[0]);
        }

        $form = $this->forms->create(CheckoutAddressType::class, $cart, [
            'logged_in' => null !== $customer && null !== $cart->getEmail(),
            'can_save_address' => null !== $customer,
            'suggest_save' => null !== $customer && [] === $saved,
            'action' => $this->urls->path('checkout', ['step' => 'address']),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $customer && $form->has('saveAddress') && $form->get('saveAddress')->getData()) {
                $this->addresses->addIfNew($customer, $cart->getShippingAddress());
            }
            $cart->touch();
            $this->em->flush();
            $this->page->redirect($this->urls->path('checkout', ['step' => 'shipping']));

            return;
        }
        $this->finish($form, $cart);
        $this->data['saved_addresses'] = array_map(static fn (Address $a): array => ['id' => $a->getId(), 'label' => $a->getLabel(), 'lines' => $a->getPostalAddress()->getLines(), 'data' => $a->getPostalAddress()->toArray()], $saved);
    }

    private function shippingAndPayment(Request $request, Cart $cart): void
    {
        $shippingMethods = $this->shipping->availableFor($cart);
        $paymentMethods = $this->payment->availableFor($cart);
        if (null === $cart->getShippingMethod() || !isset($shippingMethods[$cart->getShippingMethod()])) {
            $cart->setShippingMethod(array_key_first($shippingMethods));
        }
        if (null === $cart->getPaymentMethod() || !isset($paymentMethods[$cart->getPaymentMethod()])) {
            $cart->setPaymentMethod(array_key_first($paymentMethods));
        }

        $form = $this->forms->create(ShippingPaymentType::class, $cart, [
            'shipping_methods' => $shippingMethods,
            'payment_methods' => $paymentMethods,
            'action' => $this->urls->path('checkout', ['step' => 'shipping']),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $cart->touch();
            $this->em->flush();
            $this->page->redirect($this->urls->path('checkout', ['step' => 'review']));

            return;
        }

        $subtotal = $this->calculator->calculate($cart, '')->getNetSubtotal();
        $costs = [];
        foreach ($shippingMethods as $code => $method) {
            $costs[$code] = $method->calculate($cart, $subtotal);
        }
        $this->finish($form, $cart);
        $this->data += ['shipping_methods' => $shippingMethods, 'shipping_costs' => $costs, 'payment_methods' => $paymentMethods];
    }

    private function review(Request $request, Cart $cart): void
    {
        $form = $this->forms->create(PlaceOrderType::class, null, [
            'terms_url' => $this->settings->getTermsUrl() ?? '#',
            'action' => $this->urls->path('checkout', ['step' => 'review']),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $order = $this->processor->placeOrder($cart, $request->getLocale());
            } catch (CartException $e) {
                $this->flash($request, 'error', $this->translator->trans($e->getMessage(), $e->getParameters(), 'AstraCheckout'));
                $this->page->redirect($this->urls->path('cart'));

                return;
            }
            $this->context->forget();
            $request->getSession()->set('_astra_last_order', $order->getToken());
            $response = $this->payment->get($order->getPaymentMethodCode())?->process($order, $request);
            null !== $response
                ? $this->page->replaceWith($response)
                : $this->page->redirect($this->urls->path('confirmation', ['token' => $order->getToken()]));

            return;
        }
        $this->finish($form, $cart);
        $this->data += [
            'shipping_method' => $this->shipping->get((string) $cart->getShippingMethod()),
            'payment_method' => $this->payment->get((string) $cart->getPaymentMethod()),
        ];
    }

    private function finish(FormInterface $form, Cart $cart): void
    {
        if ($form->isSubmitted()) {
            $this->page->setStatus(422);
            // Invalid input is never flushed with a later change of the cart.
            $this->em->refresh($cart);
        }
        $this->form = $form->createView();
        $this->totals = $this->calculator->calculate($cart);
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add($type, $message);
        }
    }
}
