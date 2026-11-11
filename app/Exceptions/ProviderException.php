<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable = false,
        public readonly int $retryAfter = 30,
        public readonly ?string $requestId = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {
        // Never retain the provider response or the HTTP exception: they may contain secrets.
        parent::__construct($errorCode);
    }
}
