<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenerationIndexRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final readonly class GenerationController
{
    public function __construct(private GenerationService $service) {}

    public function store(StoreGenerationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $generation = $this->service->create($user->id, (string) $request->validated('idempotency_key'), $request->toData());

        return (new GenerationResource($generation))->response()
            ->setStatusCode($generation->wasRecentlyCreated ? 202 : 200)
            ->header('Location', route('generations.show', $generation))
            ->header('Idempotency-Replayed', $generation->wasRecentlyCreated ? 'false' : 'true');
    }

    public function index(GenerationIndexRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{status?: string, from?: string, to?: string, per_page?: int|string} $filters */
        $filters = $request->validated();

        return GenerationResource::collection($this->service->history($user->id, $filters));
    }

    public function show(Generation $generation): GenerationResource
    {
        Gate::authorize('view', $generation);

        return new GenerationResource($generation);
    }
}
