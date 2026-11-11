<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GenerationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessGenerationJob implements ShouldQueue
{
    use Queueable;

    // Provider retries are persisted in MySQL; blind queue retries could repeat a paid call.
    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $generationId)
    {
        $this->onQueue('generations');
    }

    public function handle(GenerationProcessor $processor): void
    {
        $processor->process($this->generationId);
    }
}
