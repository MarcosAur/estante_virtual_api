<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_page' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['want_to_read', 'reading', 'read'])],
            'finished_at' => ['nullable', 'date'],
        ];
    }
}
