<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportSuggestedMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['want_to_read', 'reading', 'read', 'want_to_watch', 'watching', 'watched']),
            ],
        ];
    }
}
