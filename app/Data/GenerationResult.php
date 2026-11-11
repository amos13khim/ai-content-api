<?php

declare(strict_types=1);

namespace App\Data;

final readonly class GenerationResult
{
    public function __construct(
        public string $text,
        public string $responseId,
        public ?string $requestId = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
