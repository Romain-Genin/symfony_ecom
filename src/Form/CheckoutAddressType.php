<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Form;

use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['logged_in']) {
            $builder->add('email', EmailType::class, [
                'label' => 'checkout.email',
                'help' => 'checkout.email_help',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            ]);
        }

        $builder
            ->add('shippingAddress', PostalAddressType::class, [
                'label' => 'checkout.shipping_address',
                'autocomplete_section' => 'shipping',
                'phone_required' => true,
                'constraints' => [new Assert\Valid()],
            ])
            ->add('billingSameAsShipping', CheckboxType::class, [
                'label' => 'checkout.billing_same',
                'required' => false,
            ])
            ->add('billingAddress', PostalAddressType::class, [
                'label' => 'checkout.billing_address',
                'autocomplete_section' => 'billing',
                'required' => false,
                'constraints' => [new Assert\Valid()],
            ]);

        if ($options['can_save_address']) {
            $builder->add('saveAddress', CheckboxType::class, [
                'label' => 'checkout.save_address',
                'mapped' => false,
                'required' => false,
                'data' => $options['suggest_save'],
            ]);
        }

        // Billing address only validated (and overwritten) when it differs from the shipping one.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (\is_array($data) && !empty($data['billingSameAsShipping'])) {
                $event->getForm()->remove('billingAddress');
                unset($data['billingAddress']);
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cart::class,
            'translation_domain' => 'AstraCheckout',
            'logged_in' => false,
            'can_save_address' => false,
            'suggest_save' => false,
        ]);
    }
}
