<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ProcessEnvironment;
use Tests\TestCase;

final class MySqlConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use ProcessEnvironment;

    public function test_concurrent_identical_requests_create_exactly_one_generation(): void
    {
        $user = User::factory()->create();
        [$results] = $this->concurrently('create', (string) $user->id);
        $this->assertCount(1, array_unique(array_column($results, 'id')));
        $this->assertSame(1, count(array_filter(array_column($results, 'created'))));
        $this->assertDatabaseCount('generations', 1);
    }

    public function test_competing_workers_make_only_one_provider_call(): void
    {
        $generation = Generation::factory()->create();
        [, $calls] = $this->concurrently('process', $generation->id);
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('generation_attempts', 1);
        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
    }

    private function concurrently(string $mode, string $argument): array
    {
        $directory = storage_path('framework/test-'.Str::uuid());
        mkdir($directory, 0777, true);
        $processes = [];
        try {
            for ($i = 0; $i < 4; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent.php'), $mode, $argument, $directory, (string) $i], base_path(), $this->processEnvironment(), timeout: 20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (count(glob($directory.'/ready-*') ?: []) < 4 && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertCount(4, glob($directory.'/ready-*') ?: [], 'Child processes did not reach the concurrency barrier.');
            touch($directory.'/start');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $calls = is_file($directory.'/calls') ? count(file($directory.'/calls', FILE_IGNORE_NEW_LINES)) : 0;

            return [$results, $calls];
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
