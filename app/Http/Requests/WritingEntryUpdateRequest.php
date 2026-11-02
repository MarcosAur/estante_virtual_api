<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WritingEntryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['sometimes', 'required', 'string', Rule::in(['reflection', 'reading', 'note', 'free'])],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:20000'],
            'entry_date' => ['sometimes', 'required', 'date'],
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
