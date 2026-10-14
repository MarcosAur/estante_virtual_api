<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->book_id,
            'rating' => (float) $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'has_spoiler' => (bool) $this->has_spoiler,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'book' => $this->whenLoaded('book', function () {
                return [
                    'id' => $this->book->id,
                    'title' => $this->book->title,
                    'author' => $this->book->author,
                    'cover_path' => $this->book->cover_path,
                ];
            }),
        ];
    }
}
