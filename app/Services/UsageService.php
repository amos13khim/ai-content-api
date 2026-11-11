<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UsageRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;

final readonly class UsageService
{
    public function __construct(private UsageRepository $usage, private Repository $cache) {}

    /** @return array<string, mixed> */
    public function get(int $userId, string $from, string $to): array
    {
        return $this->cache->remember("usage:v1:{$userId}:{$from}:{$to}", (int) config('ai.usage_cache_seconds'), fn (): array => [
            'period' => ['from' => $from, 'to' => $to, 'timezone' => 'UTC'],
            ...$this->usage->aggregate($userId, CarbonImmutable::parse($from), CarbonImmutable::parse($to)->addDay()),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
