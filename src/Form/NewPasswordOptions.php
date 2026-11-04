<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Form;

use Symfony\Component\Validator\Constraints as Assert;

/** Shared options of "new password" fields (single field + show/hide toggle, no confirmation field). */
final class NewPasswordOptions
{
    /** @return array<string, mixed> */
    public static function options(string $label = 'account.password'): array
    {
        return [
            'label' => $label,
            'mapped' => false,
            'help' => 'account.password_help',
            'attr' => ['autocomplete' => 'new-password', 'minlength' => 8, 'data-password-toggle' => true],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 8, max: 4096, minMessage: 'account.password_too_short'),
            ],
        ];
    }
}
