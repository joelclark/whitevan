<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'acknowledged' => ['required', 'accepted'],
        ];
    }
}
