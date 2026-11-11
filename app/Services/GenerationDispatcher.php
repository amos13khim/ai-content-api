<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcessGenerationJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerationDispatcher
{
    public function dispatch(string $id, ?CarbonInterface $availableAt = null): bool
    {
        try {
            Bus::dispatch((new ProcessGenerationJob($id))->afterCommit()->delay($availableAt));

            return true;
        } catch (Throwable) {
            // The committed queued row is the durable intent. The scheduler will retry publication.
            Log::warning('generation.dispatch_deferred', ['generation_id' => $id]);

            return false;
        }
    }
}
