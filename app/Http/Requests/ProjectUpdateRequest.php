<?php

namespace App\Http\Requests;

use App\Contexts\AccountContext;
use Illuminate\Foundation\Http\FormRequest;

class ProjectUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccountContext::class)->id() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'name',
            'site_address_line_1',
            'site_address_line_2',
            'site_city',
            'site_state',
            'site_zip',
            'notes',
        ] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed = trim($value);
                $normalized[$field] = $trimmed === '' ? null : $trimmed;
            } else {
                $normalized[$field] = $value;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'site_address_line_1' => ['nullable', 'string', 'max:255'],
            'site_address_line_2' => ['nullable', 'string', 'max:255'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_state' => ['nullable', 'string', 'max:255'],
            'site_zip' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
