<?php

namespace App\Http\Requests\Sysops;

use Illuminate\Foundation\Http\FormRequest;

class AiAgentUpdateRequest extends FormRequest
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
            'system_prompt' => ['required', 'string', 'min:10', 'max:20000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
