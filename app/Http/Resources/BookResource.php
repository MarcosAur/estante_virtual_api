<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $progress = $this->pages > 0 ? min(100, (int) round(($this->current_page / $this->pages) * 100)) : 0;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'publisher' => $this->publisher,
            'year' => $this->year,
            'pages' => $this->pages,
            'cover_path' => $this->cover_path,
            'cover_url' => $this->cover_path ? url("/api/v1/books/{$this->id}/cover") : null,
            'synopsis' => $this->synopsis,
            'status' => $this->status,
            'current_page' => $this->current_page,
            'progress_percentage' => $progress,
            'started_at' => $this->started_at?->format('Y-m-d'),
            'finished_at' => $this->finished_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'review' => new ReviewResource($this->whenLoaded('review')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
