<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
final class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'status' => $this->status->value, 'input' => $this->input,
            'provider' => $this->provider, 'model' => $this->model, 'result' => $this->result,
            'attempts' => $this->attempt_count,
            'error' => $this->status === GenerationStatus::Failed ? ['code' => $this->error_code] : null,
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
