<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Repositories\GenerationRepository;
use App\Services\GenerationDispatcher;
use Illuminate\Console\Command;

final class RecoverGenerationsCommand extends Command
{
    protected $signature = 'generations:recover';

    protected $description = 'Republish ready jobs and close interrupted attempts without repeating ambiguous AI calls';

    public function handle(GenerationRepository $repository, GenerationDispatcher $dispatcher): int
    {
        $cutoff = now()->subSeconds((int) config('ai.processing_timeout_seconds'));
        $stale = Generation::query()->where('status', GenerationStatus::Processing)->where('started_at', '<=', $cutoff)
            ->orderBy('started_at')->limit(500)->pluck('id');
        $closed = 0;
        foreach ($stale as $id) {
            $closed += (int) $repository->failStale($id, $cutoff);
        }
        $ready = Generation::query()->where('status', GenerationStatus::Queued)->where('available_at', '<=', now())
            ->orderBy('available_at')->limit(500)->pluck('id');
        $dispatched = 0;
        foreach ($ready as $id) {
            $dispatched += (int) $dispatcher->dispatch($id);
        }
        $this->info("Dispatched: {$dispatched}; interrupted attempts closed: {$closed}.");

        return self::SUCCESS;
    }
}
