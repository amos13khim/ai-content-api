<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $generation_id
 * @property int $number
 * @property string $status
 * @property Carbon $started_at
 * @property ?Carbon $finished_at
 */
final class GenerationAttempt extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'number' => 'integer', 'input_tokens' => 'integer', 'output_tokens' => 'integer',
            'duration_ms' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime',
        ];
    }
}
