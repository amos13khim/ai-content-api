<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\ProcessGenerationJob;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_and_token_abilities_are_required(): void
    {
        $this->getJson('/api/v1/generations')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(), ['generations:read']);
        $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])->assertForbidden();
    }

    public function test_creation_and_replay_return_one_owned_job_and_conflicting_input_returns_409(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);
        $first = $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])
            ->assertAccepted()->assertHeader('Idempotency-Replayed', 'false')->assertJsonPath('data.status', 'queued');
        $id = $first->json('data.id');
        $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])
            ->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/generations', [...$this->payload(), 'tone' => 'friendly'], ['Idempotency-Key' => 'sample-key'])
            ->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->assertDatabaseCount('generations', 1);
        $this->assertDatabaseHas('generations', ['id' => $id, 'user_id' => $user->id]);
        Queue::assertPushed(ProcessGenerationJob::class, 1);
    }

    public function test_keys_are_case_sensitive_and_scoped_to_users_in_mysql(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'Sample-Key'])->assertAccepted();
        $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])->assertAccepted();
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])->assertAccepted();
        $this->assertDatabaseCount('generations', 3);
    }

    public function test_invalid_input_and_missing_header_do_not_enqueue_work(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->postJson('/api/v1/generations', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->postJson('/api/v1/generations', [...$this->payload(), 'features' => ['key' => 'value'], 'language' => 'xx'], ['Idempotency-Key' => 'sample-key'])
            ->assertUnprocessable()->assertJsonValidationErrors(['features', 'language']);
        $this->assertDatabaseCount('generations', 0);
        Queue::assertNothingPushed();
    }

    public function test_other_users_cannot_read_a_generation_or_internal_identifiers(): void
    {
        $generation = Generation::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->getJson('/api/v1/generations/'.$generation->id)->assertNotFound();
        Sanctum::actingAs(User::query()->findOrFail($generation->user_id), ['*']);
        $this->getJson('/api/v1/generations/'.$generation->id)->assertOk()
            ->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.idempotency_key')->assertJsonMissingPath('data.request_hash');
    }

    public function test_history_is_filtered_and_cursor_pagination_has_no_duplicates_on_tied_timestamps(): void
    {
        $user = User::factory()->create();
        $createdAt = now()->startOfSecond();
        $owned = Generation::factory()->count(3)->create(['user_id' => $user->id, 'status' => GenerationStatus::Succeeded, 'created_at' => $createdAt]);
        Generation::factory()->create(['user_id' => $user->id, 'status' => GenerationStatus::Failed]);
        Generation::factory()->create(['status' => GenerationStatus::Succeeded]);
        Sanctum::actingAs($user, ['*']);
        $first = $this->getJson('/api/v1/generations?status=succeeded&per_page=2')->assertOk()->assertJsonCount(2, 'data');
        $second = $this->getJson($first->json('links.next'))->assertOk()->assertJsonCount(1, 'data');
        $ids = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
        $this->assertEqualsCanonicalizing($owned->pluck('id')->all(), $ids);
    }

    public function test_write_rate_limit_is_per_authenticated_user(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create(), ['*']);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])->assertSuccessful();
        }
        $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])->assertTooManyRequests();
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->postJson('/api/v1/generations', $this->payload(), ['Idempotency-Key' => 'sample-key'])->assertAccepted();
    }
}
