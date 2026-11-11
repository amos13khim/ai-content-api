<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GenerationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class GenerationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Enum>> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(GenerationStatus::class)],
            'from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'required', 'date_format:Y-m-d', ...($this->has('from') ? ['after_or_equal:from'] : [])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
