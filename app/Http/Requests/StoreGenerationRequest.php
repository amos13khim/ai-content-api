<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\GenerationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'regex:/\A[A-Za-z0-9._:-]{8,100}\z/'],
            'product_name' => ['required', 'string', 'max:200'],
            'features' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'features.*' => ['required', 'string', 'max:300', 'distinct'],
            'language' => ['required', Rule::in(['ru', 'en'])],
            'tone' => ['required', Rule::in(['neutral', 'friendly', 'professional'])],
        ];
    }

    public function toData(): GenerationData
    {
        /** @var array{product_name: string, features: list<string>, language: string, tone: string} $data */
        $data = $this->validated();

        return new GenerationData($data['product_name'], $data['features'], $data['language'], $data['tone']);
    }
}
