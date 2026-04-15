<?php

namespace App\Http\Requests;

use App\Contexts\AccountContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccountContext::class)->id() !== null;
    }

    /**
     * Normalize all string fields: trim whitespace, then treat
     * blank strings as null so the per-account unique rule and the
     * partial unique index both exempt blanks.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        $fields = [
            'first_name',
            'last_name',
            'company',
            'email',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'zip',
            'notes',
        ];

        foreach ($fields as $field) {
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
        $customerId = $this->route('customer')?->id;
        $accountId = app(AccountContext::class)->id();

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->ignore($customerId),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
