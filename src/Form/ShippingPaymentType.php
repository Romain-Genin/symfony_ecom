<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Form;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodInterface;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ShippingPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, ShippingMethodInterface> $shipping */
        $shipping = $options['shipping_methods'];
        /** @var array<string, PaymentMethodInterface> $payment */
        $payment = $options['payment_methods'];

        $builder
            ->add('shippingMethod', ChoiceType::class, [
                'label' => 'checkout.shipping_method',
                'expanded' => true,
                'choices' => array_combine(array_keys($shipping), array_keys($shipping)),
                'choice_label' => static fn (string $code): string => $shipping[$code]->getLabel(),
                'choice_attr' => static fn (string $code): array => ['data-description' => (string) $shipping[$code]->getDescription()],
                'constraints' => [new Assert\NotBlank(message: 'checkout.error.choose_shipping'), new Assert\Choice(choices: array_keys($shipping))],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'checkout.payment_method',
                'expanded' => true,
                'choices' => array_combine(array_keys($payment), array_keys($payment)),
                'choice_label' => static fn (string $code): string => $payment[$code]->getLabel(),
                'choice_attr' => static fn (string $code): array => ['data-description' => (string) $payment[$code]->getDescription()],
                'constraints' => [new Assert\NotBlank(message: 'checkout.error.choose_payment'), new Assert\Choice(choices: array_keys($payment))],
            ])
            ->add('customerNote', TextareaType::class, [
                'label' => 'checkout.note',
                'required' => false,
                'help' => 'checkout.note_help',
                'attr' => ['rows' => 3, 'maxlength' => 1000],
                'constraints' => [new Assert\Length(max: 1000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Cart::class, 'translation_domain' => 'AstraCheckout']);
        $resolver->setRequired(['shipping_methods', 'payment_methods']);
    }
}
