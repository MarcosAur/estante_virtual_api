<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TagStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tagId = $this->route('tag') ? (is_string($this->route('tag')) ? $this->route('tag') : $this->route('tag')->id) : null;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tags', 'name')
                    ->where('user_id', Auth::id())
                    ->ignore($tagId),
            ],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
