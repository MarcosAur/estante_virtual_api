<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movie_id' => $this->movie_id,
            'rating' => (float) $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'has_spoiler' => (bool) $this->has_spoiler,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'movie' => $this->whenLoaded('movie', function () {
                return [
                    'id' => $this->movie->id,
                    'title' => $this->movie->title,
                    'director' => $this->movie->director,
                    'poster_path' => $this->movie->poster_path,
                ];
            }),
        ];
    }
}
