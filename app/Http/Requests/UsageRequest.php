<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['from' => $this->input('from', now()->subDays(29)->toDateString()), 'to' => $this->input('to', now()->toDateString())]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && CarbonImmutable::parse($this->string('from')->toString())->diffInDays(CarbonImmutable::parse($this->string('to')->toString())) > 92) {
                $validator->errors()->add('to', 'The period must not exceed 93 days.');
            }
        }];
    }
}
