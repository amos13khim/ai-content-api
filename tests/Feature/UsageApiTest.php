<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class UsageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_do_not_multiply_generations_by_attempts_or_leak_other_users(): void
    {
        $user = User::factory()->create();
        $generation = Generation::factory()->create(['user_id' => $user->id, 'status' => GenerationStatus::Succeeded]);
        $generation->attempts()->create(['number' => 1, 'status' => 'failed', 'error_code' => 'rate_limited', 'started_at' => now(), 'finished_at' => now()]);
        $generation->attempts()->create(['number' => 2, 'status' => 'succeeded', 'input_tokens' => 10, 'output_tokens' => 20, 'started_at' => now(), 'finished_at' => now()]);
        Generation::factory()->create(['user_id' => $user->id, 'status' => GenerationStatus::Failed]);
        Generation::factory()->create(['user_id' => $user->id, 'created_at' => now()->subYears(2)]);
        Generation::factory()->count(3)->create();
        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/v1/usage')->assertOk()->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.succeeded', 1)->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.input_tokens', 10)->assertJsonPath('data.output_tokens', 20)
            ->assertJsonPath('data.attempts_with_unreported_usage', 1);
    }

    public function test_cache_has_a_bounded_lifetime_and_user_specific_keys(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/v1/usage')->assertOk()->assertJsonPath('data.total', 0);
        Generation::factory()->create(['user_id' => $user->id]);
        $this->getJson('/api/v1/usage')->assertJsonPath('data.total', 0);
        $this->travel(61)->seconds();
        $this->getJson('/api/v1/usage')->assertJsonPath('data.total', 1);
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->getJson('/api/v1/usage')->assertJsonPath('data.total', 0);
    }

    public function test_invalid_or_excessively_wide_periods_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->getJson('/api/v1/usage?from=2026-02-30&to=2026-03-01')->assertUnprocessable();
        $this->getJson('/api/v1/usage?from=2026-01-01&to=2026-12-31')->assertUnprocessable();
        $this->getJson('/api/v1/usage?from=2026-03-01&to=2026-02-01')->assertUnprocessable();
    }
}
