<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Form;

use StudioAstra\AstraCheckoutBundle\Customer\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'account.first_name', 'attr' => ['autocomplete' => 'given-name']])
            ->add('lastName', TextType::class, ['label' => 'account.last_name', 'attr' => ['autocomplete' => 'family-name']])
            ->add('email', EmailType::class, ['label' => 'account.email', 'attr' => ['autocomplete' => 'email']])
            ->add('plainPassword', PasswordType::class, NewPasswordOptions::options());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Customer::class, 'translation_domain' => 'AstraCheckout']);
    }
}
