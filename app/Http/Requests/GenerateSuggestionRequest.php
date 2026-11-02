<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['book', 'movie'])],
            'prompt' => ['required', 'string', 'max:1000'],
            'use_history' => ['sometimes', 'boolean'],
        ];
    }
}
