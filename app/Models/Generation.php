<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\GenerationData;
use App\Enums\GenerationStatus;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $request_hash
 * @property array{product_name: string, features: list<string>, language: string, tone: string} $input
 * @property GenerationStatus $status
 * @property string $provider
 * @property string $model
 * @property int $attempt_count
 * @property ?string $result
 * @property ?string $error_code
 * @property ?Carbon $available_at
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input' => 'array', 'status' => GenerationStatus::class,
            'user_id' => 'integer', 'attempt_count' => 'integer',
            'available_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function data(): GenerationData
    {
        return new GenerationData($this->input['product_name'], $this->input['features'], $this->input['language'], $this->input['tone']);
    }

    /** @return HasMany<GenerationAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(GenerationAttempt::class);
    }
}
