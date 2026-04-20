<?php

namespace App\Http\Requests\Sysops;

use Illuminate\Foundation\Http\FormRequest;

class DepositDefaultsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSysop() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'material_deposit_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'labor_deposit_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
