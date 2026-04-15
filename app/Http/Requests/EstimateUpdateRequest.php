<?php

namespace App\Http\Requests;

use App\Contexts\AccountContext;
use Illuminate\Foundation\Http\FormRequest;

class EstimateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccountContext::class)->id() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('title') && is_string($this->input('title'))) {
            $trimmed = trim((string) $this->input('title'));
            $this->merge(['title' => $trimmed === '' ? null : $trimmed]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
