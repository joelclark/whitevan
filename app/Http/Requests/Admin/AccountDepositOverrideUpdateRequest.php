<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AccountDepositOverrideUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'material_deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'labor_deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
