<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TextGenerator;
use App\Data\GenerationResult;
use App\Enums\GenerationStatus;
use App\Exceptions\ProviderException;
use App\Jobs\ProcessGenerationJob;
use App\Models\Generation;
use App\Repositories\GenerationRepository;
use App\Services\GenerationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ScriptedTextGenerator;
use Tests\TestCase;

final class GenerationProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_delivery_does_not_repeat_a_completed_provider_call(): void
    {
        $provider = $this->provider([new GenerationResult('Description', 'response-1', inputTokens: 10, outputTokens: 20)]);
        $generation = Generation::factory()->create();
        $this->process($generation);
        $this->process($generation);
        $this->assertSame(1, $provider->calls);
        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
        $this->assertSame('Description', $generation->result);
        $this->assertDatabaseHas('generation_attempts', ['generation_id' => $generation->id, 'input_tokens' => 10, 'output_tokens' => 20]);
    }

    public function test_rate_limited_attempt_is_delayed_then_succeeds_without_early_calls(): void
    {
        Queue::fake();
        $provider = $this->provider([new ProviderException('rate_limited', true, 120), new GenerationResult('Done', 'response-2')]);
        $generation = Generation::factory()->create();
        $this->process($generation);
        $this->assertSame(GenerationStatus::Queued, $generation->refresh()->status);
        $this->process($generation);
        $this->assertSame(1, $provider->calls);
        Queue::assertPushed(ProcessGenerationJob::class, 1);
        $this->travel(121)->seconds();
        $this->process($generation);
        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
        $this->assertSame(2, $generation->attempt_count);
    }

    public function test_safe_retries_are_bounded(): void
    {
        Queue::fake();
        $provider = $this->provider(array_fill(0, 3, new ProviderException('rate_limited', true, 10)));
        $generation = Generation::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->process($generation);
            $this->travel(120)->seconds();
        }
        $this->process($generation);
        $this->assertSame(3, $provider->calls);
        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
        Queue::assertPushed(ProcessGenerationJob::class, 2);
    }

    public function test_unknown_outcome_is_terminal_and_never_automatically_repeated(): void
    {
        Queue::fake();
        $provider = $this->provider([new ProviderException('outcome_unknown')]);
        $generation = Generation::factory()->create();
        $this->process($generation);
        $this->process($generation);
        $this->assertSame(1, $provider->calls);
        $this->assertSame('outcome_unknown', $generation->refresh()->error_code);
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        Queue::assertNothingPushed();
    }

    public function test_recovery_closes_an_interrupted_attempt_and_rejects_a_late_result(): void
    {
        Queue::fake();
        $generation = Generation::factory()->create();
        $repository = app(GenerationRepository::class);
        [, $attempt] = $repository->claim($generation->id, 3);
        $this->travel(301)->seconds();
        $this->artisan('generations:recover')->assertSuccessful();
        $repository->complete($attempt, new GenerationResult('Too late', 'late-response'), 100);
        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
        $this->assertSame('outcome_unknown', $generation->error_code);
        $this->assertNull($generation->result);
        Queue::assertNothingPushed();
    }

    public function test_provider_configuration_changes_do_not_silently_switch_queued_work(): void
    {
        $provider = $this->provider([]);
        $generation = Generation::factory()->create(['provider' => 'openai', 'model' => 'previous-model']);
        $this->process($generation);
        $this->assertSame(0, $provider->calls);
        $this->assertSame('provider_configuration_changed', $generation->refresh()->error_code);
    }

    private function process(Generation $generation): void
    {
        app(GenerationProcessor::class)->process($generation->id);
    }

    private function provider(array $responses): ScriptedTextGenerator
    {
        $provider = new ScriptedTextGenerator($responses);
        $this->app->instance(TextGenerator::class, $provider);

        return $provider;
    }
}
