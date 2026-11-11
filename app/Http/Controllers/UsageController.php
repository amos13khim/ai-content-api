<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UsageRequest;
use App\Models\User;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;

final readonly class UsageController
{
    public function __invoke(UsageRequest $request, UsageService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $service->get($user->id, (string) $request->validated('from'), (string) $request->validated('to'))]);
    }
}
