<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Form;

use RomainGenin\AstraCheckoutBundle\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'address.label', 'required' => false, 'help' => 'address.label_help', 'attr' => ['autocomplete' => 'off']])
            ->add('postalAddress', PostalAddressType::class, ['label' => false, 'constraints' => [new Assert\Valid()]])
            ->add('default', CheckboxType::class, ['label' => 'address.default', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Address::class, 'translation_domain' => 'AstraCheckout']);
    }
}
