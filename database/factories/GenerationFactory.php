<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Data\GenerationData;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Generation> */
final class GenerationFactory extends Factory
{
    public function definition(): array
    {
        $data = new GenerationData('Travel mug', ['Steel', '350 ml'], 'en', 'neutral');

        return [
            'user_id' => User::factory(), 'idempotency_key' => fake()->uuid(),
            'request_hash' => $data->fingerprint(), 'input' => $data->toArray(),
            'status' => GenerationStatus::Queued, 'provider' => 'fake', 'model' => 'demo-v1',
            'available_at' => now(),
        ];
    }
}
