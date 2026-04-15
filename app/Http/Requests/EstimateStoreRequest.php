<?php

namespace App\Http\Requests;

use App\Contexts\AccountContext;
use Illuminate\Foundation\Http\FormRequest;

class EstimateStoreRequest extends FormRequest
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
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:25600'],
        ];
    }
}
