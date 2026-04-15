<?php

namespace App\Http\Requests;

use App\Contexts\AccountContext;
use Illuminate\Foundation\Http\FormRequest;

class EstimateInterviewAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccountContext::class)->id() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'question_key' => ['required', 'string', 'max:64'],
            'room_id' => ['nullable', 'integer'],
            // Domain validation (option whitelist, count range, etc.) lives
            // in the Question class for the given question_key. This rule
            // only ensures a value is present.
            'value' => ['present'],
        ];
    }
}
