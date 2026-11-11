<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\GenerationStatus;
use App\Jobs\ProcessGenerationJob;
use App\Models\Generation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ProcessEnvironment;
use Tests\TestCase;

final class RedisWorkerTest extends TestCase
{
    use DatabaseMigrations;
    use ProcessEnvironment;

    public function test_real_redis_queue_is_consumed_by_a_separate_worker_process(): void
    {
        $queue = 'integration-'.Str::uuid();
        $generation = Generation::factory()->create();
        Bus::dispatch((new ProcessGenerationJob($generation->id))->onConnection('redis')->onQueue($queue));
        $worker = new Process([PHP_BINARY, 'artisan', 'queue:work', 'redis', '--queue='.$queue, '--once', '--tries=1', '--timeout=20'], base_path(), $this->processEnvironment(), timeout: 30);
        $worker->run();
        $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
        $this->assertSame(1, $generation->attempt_count);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_real_redis_cache_expires_entries(): void
    {
        $key = 'integration:'.Str::uuid();
        $cache = Cache::store('redis');
        try {
            $cache->put($key, 'cached', 1);
            $this->assertSame('cached', $cache->get($key));
            usleep(1100000);
            $this->assertNull($cache->get($key));
        } finally {
            $cache->forget($key);
        }
    }
}
