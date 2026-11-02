<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WritingEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(['reflection', 'reading', 'note', 'free'])],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'entry_date' => ['required', 'date'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'mood' => ['nullable', 'string', 'max:50'],
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
