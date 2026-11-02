<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuggestedMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasActiveResource = $this->mediable && !$this->mediable->trashed();
        $status = $hasActiveResource ? $this->mediable->status : 'suggested';
        $resourceId = $hasActiveResource ? $this->mediable_id : null;

        return [
            'id' => $this->id,
            'suggestion_id' => $this->suggestion_id,
            'position' => $this->position,
            'title' => $this->title,
            'creator' => $this->creator,
            'year' => $this->year,
            'synopsis' => $this->synopsis,
            'reason' => $this->reason,
            'status' => $status,
            'mediable_type' => $hasActiveResource ? $this->mediable_type : null,
            'mediable_id' => $resourceId,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
