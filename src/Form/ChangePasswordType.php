<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['require_current']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'account.current_password',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new UserPassword(message: 'account.wrong_password')],
            ]);
        }
        $builder->add('newPassword', PasswordType::class, NewPasswordOptions::options('account.new_password'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'AstraCheckout', 'require_current' => true]);
    }
}
