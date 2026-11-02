<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'director' => $this->director,
            'year' => $this->year,
            'runtime_minutes' => $this->runtime_minutes,
            'genre' => $this->genre,
            'synopsis' => $this->synopsis,
            'poster_path' => $this->poster_path,
            'poster_url' => $this->poster_path ? "/api/v1/movies/{$this->id}/poster?v=" . $this->updated_at?->timestamp : null,
            'tmdb_id' => $this->tmdb_id,
            'status' => $this->status,
            'watched_at' => $this->watched_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'review' => new MovieReviewResource($this->whenLoaded('review')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
