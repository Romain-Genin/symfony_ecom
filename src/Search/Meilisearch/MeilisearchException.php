<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Search\Meilisearch;

final class MeilisearchException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, public readonly ?string $errorCode = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
