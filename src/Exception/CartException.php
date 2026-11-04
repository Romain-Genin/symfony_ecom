<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Exception;

/** Business error shown to the visitor; the message is a translation key of the AstraCheckout domain. */
class CartException extends \DomainException
{
    /**
     * @param array<string, string|int> $parameters
     */
    public function __construct(string $translationKey, private readonly array $parameters = [])
    {
        parent::__construct($translationKey);
    }

    /** @return array<string, string|int> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
