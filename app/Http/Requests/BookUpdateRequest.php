<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BookUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1000', 'max:' . (date('Y') + 5)],
            'pages' => ['nullable', 'integer', 'min:0'],
            'cover_path' => ['nullable', 'string'],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'synopsis' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['want_to_read', 'reading', 'read'])],
            'current_page' => ['nullable', 'integer', 'min:0'],
            'started_at' => ['nullable', 'date'],
            'finished_at' => ['nullable', 'date'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'string',
                Rule::exists('tags', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id());
                }),
            ],
        ];
    }
}
