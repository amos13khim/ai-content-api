<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TextGenerator;
use App\Data\GenerationData;
use App\Exceptions\IdempotencyConflict;
use App\Models\Generation;
use App\Repositories\GenerationRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class GenerationService
{
    public function __construct(private GenerationRepository $generations, private TextGenerator $generator, private GenerationDispatcher $dispatcher) {}

    public function create(int $userId, string $key, GenerationData $data): Generation
    {
        $generation = $this->generations->createOrFind($userId, $key, $data, $this->generator->name(), $this->generator->model());
        if (! hash_equals($generation->request_hash, $data->fingerprint())) {
            throw new IdempotencyConflict;
        }
        if ($generation->wasRecentlyCreated) {
            $this->dispatcher->dispatch($generation->id);
        }

        return $generation;
    }

    /**
     * @param  array{status?: string, from?: string, to?: string, per_page?: int|string}  $filters
     * @return CursorPaginator<int, Generation>
     */
    public function history(int $userId, array $filters): CursorPaginator
    {
        return $this->generations->history($userId, $filters);
    }
}
