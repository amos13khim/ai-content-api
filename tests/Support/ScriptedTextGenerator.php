<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\TextGenerator;
use App\Data\GenerationData;
use App\Data\GenerationResult;
use App\Exceptions\ProviderException;
use LogicException;

final class ScriptedTextGenerator implements TextGenerator
{
    public int $calls = 0;

    /** @param list<GenerationResult|ProviderException> $responses */
    public function __construct(private array $responses) {}

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
        $this->calls++;
        $response = array_shift($this->responses) ?? throw new LogicException('Unexpected provider call.');
        if ($response instanceof ProviderException) {
            throw $response;
        }

        return $response;
    }
}
