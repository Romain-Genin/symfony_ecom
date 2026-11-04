<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PlaceOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('acceptTerms', CheckboxType::class, [
            'label' => 'checkout.accept_terms',
            'label_html' => true,
            'label_translation_parameters' => ['%url%' => htmlspecialchars((string) $options['terms_url'])],
            'constraints' => [new Assert\IsTrue(message: 'checkout.error.accept_terms')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'AstraCheckout', 'terms_url' => '#']);
    }
}
