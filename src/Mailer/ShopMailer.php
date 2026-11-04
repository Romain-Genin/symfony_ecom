<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Mailer;

use Psr\Log\LoggerInterface;
use RomainGenin\AstraCheckoutBundle\Customer\Customer;
use RomainGenin\AstraCheckoutBundle\Entity\Order;
use RomainGenin\AstraCheckoutBundle\Enum\OrderStatus;
use RomainGenin\AstraCheckoutBundle\Event\OrderPlacedEvent;
use RomainGenin\AstraCheckoutBundle\Event\OrderStatusChangedEvent;
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodRegistry;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Transactional e-mails. Requires symfony/mailer and astra_checkout.mailer.from; otherwise
 * nothing is sent (and a notice is logged). Templates: @AstraCheckout/email/*.html.twig.
 */
final class ShopMailer
{
    public function __construct(
        private readonly ShopSettings $settings,
        private readonly PaymentMethodRegistry $payments,
        private readonly ?MailerInterface $mailer = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[AsEventListener]
    public function onOrderPlaced(OrderPlacedEvent $event): void
    {
        $order = $event->order;
        $this->send($order->getEmail(), 'email.order_confirmation.subject', ['%number%' => $order->getNumber()], '@AstraCheckout/email/order_confirmation.html.twig', [
            'order' => $order,
            'instructions' => $this->payments->get($order->getPaymentMethodCode())?->getInstructions($order),
        ], $order->getLocale());
    }

    #[AsEventListener]
    public function onOrderStatusChanged(OrderStatusChangedEvent $event): void
    {
        if (!\in_array($event->to, [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            return;
        }
        $order = $event->order;
        $this->send($order->getEmail(), 'email.order_status.subject', ['%number%' => $order->getNumber()], '@AstraCheckout/email/order_status.html.twig', [
            'order' => $order,
        ], $order->getLocale());
    }

    public function sendPasswordReset(Customer $customer, string $resetUrl, string $locale): bool
    {
        return $this->send($customer->getEmail(), 'email.password_reset.subject', [], '@AstraCheckout/email/password_reset.html.twig', [
            'customer' => $customer,
            'reset_url' => $resetUrl,
            'ttl_minutes' => intdiv($this->settings->getPasswordResetTtl(), 60),
        ], $locale);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $subjectParameters
     */
    private function send(string $to, string $subject, array $subjectParameters, string $template, array $context, string $locale): bool
    {
        $from = $this->settings->getMailerFrom();
        if (null === $this->mailer || null === $from) {
            $this->logger?->notice('AstraCheckout: e-mail "{template}" not sent (symfony/mailer or astra_checkout.mailer.from missing).', ['template' => $template]);

            return false;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($from, (string) $this->settings->getMailerFromName()))
            ->to($to)
            ->subject($this->translator?->trans($subject, $subjectParameters + ['%shop%' => $this->settings->getShopName()], 'AstraCheckout', $locale) ?? $subject)
            ->htmlTemplate($template)
            ->locale($locale)
            ->context($context + ['shop_name' => $this->settings->getShopName()]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // An e-mail failure must never break the checkout.
            $this->logger?->error('AstraCheckout: e-mail sending failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return false;
        }

        return true;
    }
}
