<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MovieStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'director' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1880', 'max:' . (date('Y') + 5)],
            'runtime_minutes' => ['nullable', 'integer', 'min:0'],
            'genre' => ['nullable', 'string', 'max:255'],
            'synopsis' => ['nullable', 'string'],
            'poster_path' => ['nullable', 'string'],
            'poster' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'tmdb_id' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['want_to_watch', 'watching', 'watched'])],
            'watched_at' => ['nullable', 'date'],
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
