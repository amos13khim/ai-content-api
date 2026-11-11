<?php

declare(strict_types=1);

namespace App\Data;

final readonly class GenerationData
{
    /** @param list<string> $features */
    public function __construct(
        public string $productName,
        public array $features,
        public string $language,
        public string $tone,
    ) {}

    /** @return array{product_name: string, features: list<string>, language: string, tone: string} */
    public function toArray(): array
    {
        return ['product_name' => $this->productName, 'features' => $this->features, 'language' => $this->language, 'tone' => $this->tone];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
