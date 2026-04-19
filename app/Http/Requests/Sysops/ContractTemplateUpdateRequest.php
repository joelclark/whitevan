<?php

namespace App\Http\Requests\Sysops;

use Illuminate\Foundation\Http\FormRequest;

class ContractTemplateUpdateRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:10', 'max:50000'],
        ];
    }
}
