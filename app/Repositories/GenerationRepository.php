<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\GenerationData;
use App\Data\GenerationResult;
use App\Enums\GenerationStatus;
use App\Exceptions\ProviderException;
use App\Models\Generation;
use App\Models\GenerationAttempt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

final class GenerationRepository
{
    public function createOrFind(int $userId, string $key, GenerationData $data, string $provider, string $model): Generation
    {
        // One insert is atomic. Keep autocommit so a losing concurrent insert can
        // read the winner without a stale MySQL REPEATABLE READ snapshot.
        return Generation::query()->firstOrCreate(
            ['user_id' => $userId, 'idempotency_key' => $key],
            ['request_hash' => $data->fingerprint(), 'input' => $data->toArray(),
                'provider' => $provider, 'model' => $model, 'status' => GenerationStatus::Queued, 'available_at' => now()],
        );
    }

    /**
     * @param  array{status?: string, from?: string, to?: string, per_page?: int|string}  $filters
     * @return CursorPaginator<int, Generation>
     */
    public function history(int $userId, array $filters): CursorPaginator
    {
        $query = Generation::query()->where('user_id', $userId);
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (isset($filters['to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'])->addDay());
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 20))->withQueryString();
    }

    /** @return array{Generation, GenerationAttempt}|null */
    public function claim(string $id, int $maxAttempts): ?array
    {
        return DB::transaction(function () use ($id, $maxAttempts): ?array {
            $generation = Generation::query()->lockForUpdate()->find($id);
            if ($generation === null || $generation->status !== GenerationStatus::Queued || $generation->available_at?->isFuture()) {
                return null;
            }
            if ($generation->attempt_count >= $maxAttempts) {
                $generation->update(['status' => GenerationStatus::Failed, 'error_code' => 'attempts_exhausted', 'completed_at' => now()]);

                return null;
            }
            $generation->update([
                'status' => GenerationStatus::Processing, 'attempt_count' => $generation->attempt_count + 1,
                'started_at' => now(), 'available_at' => null, 'error_code' => null,
            ]);
            $attempt = $generation->attempts()->create(['number' => $generation->attempt_count, 'started_at' => now()]);

            return [$generation, $attempt];
        });
    }

    public function complete(GenerationAttempt $attempt, GenerationResult $result, int $durationMs): void
    {
        DB::transaction(function () use ($attempt, $result, $durationMs): void {
            $generation = $this->lockActiveAttempt($attempt);
            if ($generation === null) {
                return;
            }
            $attempt->update([
                'status' => 'succeeded', 'finished_at' => now(), 'duration_ms' => $durationMs,
                'request_id' => $result->requestId, 'response_id' => $result->responseId,
                'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens,
            ]);
            $generation->update(['status' => GenerationStatus::Succeeded, 'result' => $result->text, 'completed_at' => now()]);
        });
    }

    public function recordFailure(GenerationAttempt $attempt, ProviderException $error, int $durationMs, ?CarbonInterface $retryAt): bool
    {
        return DB::transaction(function () use ($attempt, $error, $durationMs, $retryAt): bool {
            $generation = $this->lockActiveAttempt($attempt);
            if ($generation === null) {
                return false;
            }
            $attempt->update([
                'status' => 'failed', 'error_code' => $error->errorCode, 'finished_at' => now(),
                'duration_ms' => $durationMs, 'request_id' => $error->requestId,
                'input_tokens' => $error->inputTokens, 'output_tokens' => $error->outputTokens,
            ]);
            $generation->update([
                'status' => $retryAt === null ? GenerationStatus::Failed : GenerationStatus::Queued,
                'error_code' => $error->errorCode, 'available_at' => $retryAt,
                'completed_at' => $retryAt === null ? now() : null,
            ]);

            return true;
        });
    }

    public function failStale(string $id, CarbonInterface $cutoff): bool
    {
        return DB::transaction(function () use ($id, $cutoff): bool {
            $generation = Generation::query()->lockForUpdate()->find($id);
            if ($generation === null || $generation->status !== GenerationStatus::Processing || $generation->started_at?->gt($cutoff)) {
                return false;
            }
            $generation->attempts()->where('number', $generation->attempt_count)->where('status', 'started')->update([
                'status' => 'failed', 'error_code' => 'outcome_unknown', 'finished_at' => now(),
            ]);
            $generation->update(['status' => GenerationStatus::Failed, 'error_code' => 'outcome_unknown', 'completed_at' => now()]);

            return true;
        });
    }

    private function lockActiveAttempt(GenerationAttempt $attempt): ?Generation
    {
        $generation = Generation::query()->lockForUpdate()->find($attempt->generation_id);

        return $generation?->status === GenerationStatus::Processing && $generation->attempt_count === $attempt->number ? $generation : null;
    }
}
