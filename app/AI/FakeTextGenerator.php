<?php

declare(strict_types=1);

namespace App\AI;

use App\Contracts\TextGenerator;
use App\Data\GenerationData;
use App\Data\GenerationResult;

final class FakeTextGenerator implements TextGenerator
{
    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'demo-v1';
    }

    public function generate(GenerationData $data): GenerationResult
    {
        $label = $data->language === 'ru' ? 'Особенности' : 'Features';
        $text = $data->productName.'. '.$label.': '.implode('; ', $data->features).'.';

        // Deterministic fixture, not a language model. Demo usage is intentionally zero.
        return new GenerationResult($text, 'demo_'.$data->fingerprint(), inputTokens: 0, outputTokens: 0);
    }
}
