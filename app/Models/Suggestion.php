<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Suggestion extends Model
{
    use HasFactory, HasUlids, BelongsToUser;

    /**
     * user_id is deliberately omitted to enforce automatic auth assignment and isolation
     */
    protected $fillable = [
        'type',
        'prompt',
        'model',
        'streaming_services',
        'use_history',
    ];

    protected function casts(): array
    {
        return [
            'streaming_services' => 'array',
            'use_history' => 'boolean',
        ];
    }

    public function media(): HasMany
    {
        return $this->hasMany(SuggestedMedia::class)->orderBy('position', 'asc');
    }
}
