<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\GenerationData;
use App\Enums\GenerationStatus;
use App\Jobs\ProcessGenerationJob;
use App\Models\Generation;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

final class RecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_outage_does_not_lose_the_persisted_request(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Redis unavailable'));
        $user = User::factory()->create();
        $generation = app(GenerationService::class)->create($user->id, 'sample-key', new GenerationData('Mug', ['Steel'], 'en', 'neutral'));
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued']);
    }

    public function test_recovery_republishes_due_jobs_but_leaves_future_jobs_and_active_workers_alone(): void
    {
        Queue::fake();
        $ready = Generation::factory()->create();
        Generation::factory()->create(['available_at' => now()->addHour()]);
        Generation::factory()->create(['status' => GenerationStatus::Processing, 'started_at' => now()]);
        $this->artisan('generations:recover')->assertSuccessful();
        Queue::assertPushed(ProcessGenerationJob::class, fn (ProcessGenerationJob $job): bool => $job->generationId === $ready->id);
        Queue::assertPushed(ProcessGenerationJob::class, 1);
    }
}
