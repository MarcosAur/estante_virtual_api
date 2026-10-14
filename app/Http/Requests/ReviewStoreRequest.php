<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', 'min:0.5', 'max:5.0'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'has_spoiler' => ['nullable', 'boolean'],
        ];
    }
}
