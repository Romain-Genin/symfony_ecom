<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Form;

use RomainGenin\AstraCheckoutBundle\Entity\PostalAddress;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Every field carries its "autocomplete" token (WCAG 1.3.5 – Identify input purpose): browsers
 * and password managers fill the address in one tap, which also helps people with motor or
 * cognitive impairments.
 */
final class PostalAddressType extends AbstractType
{
    public function __construct(private readonly ShopSettings $settings)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $section = $options['autocomplete_section'] ? $options['autocomplete_section'] . ' ' : '';
        $ac = static fn (string $token): array => ['autocomplete' => $section . $token];

        $builder
            ->add('firstName', TextType::class, ['label' => 'address.first_name', 'attr' => $ac('given-name')])
            ->add('lastName', TextType::class, ['label' => 'address.last_name', 'attr' => $ac('family-name')])
            ->add('company', TextType::class, ['label' => 'address.company', 'required' => false, 'attr' => $ac('organization')])
            ->add('street', TextType::class, ['label' => 'address.street', 'attr' => $ac('address-line1')])
            ->add('street2', TextType::class, ['label' => 'address.street2', 'required' => false, 'help' => 'address.street2_help', 'attr' => $ac('address-line2')])
            ->add('postalCode', TextType::class, ['label' => 'address.postal_code', 'attr' => $ac('postal-code') + ['inputmode' => 'text', 'size' => 10]])
            ->add('city', TextType::class, ['label' => 'address.city', 'attr' => $ac('address-level2')])
            ->add('countryCode', CountryType::class, [
                'label' => 'address.country',
                'preferred_choices' => [],
                'choice_filter' => [] === $this->settings->getCountries() ? null : fn (?string $code): bool => \in_array($code, $this->settings->getCountries(), true),
                'placeholder' => \count($this->settings->getCountries()) === 1 ? false : 'address.country_placeholder',
                'attr' => $ac('country'),
            ])
            ->add('phone', TelType::class, ['label' => 'address.phone', 'required' => $options['phone_required'], 'help' => 'address.phone_help', 'attr' => $ac('tel')]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PostalAddress::class,
            'translation_domain' => 'AstraCheckout',
            'autocomplete_section' => null,
            'phone_required' => false,
        ]);
        $resolver->setAllowedTypes('autocomplete_section', ['null', 'string']);
    }
}
