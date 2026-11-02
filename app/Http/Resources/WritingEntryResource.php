<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WritingEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'source_label' => $this->source_label,
            'mood' => $this->mood,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
