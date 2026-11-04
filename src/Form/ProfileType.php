<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Form;

use RomainGenin\AstraCheckoutBundle\Customer\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'account.first_name', 'attr' => ['autocomplete' => 'given-name']])
            ->add('lastName', TextType::class, ['label' => 'account.last_name', 'attr' => ['autocomplete' => 'family-name']])
            ->add('email', EmailType::class, ['label' => 'account.email', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'account.phone', 'required' => false, 'attr' => ['autocomplete' => 'tel']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Customer::class, 'translation_domain' => 'AstraCheckout']);
    }
}
