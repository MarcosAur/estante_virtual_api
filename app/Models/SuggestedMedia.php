<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SuggestedMedia extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'suggested_media';

    protected $fillable = [
        'suggestion_id',
        'position',
        'title',
        'creator',
        'year',
        'synopsis',
        'reason',
        'mediable_type',
        'mediable_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'year' => 'integer',
        ];
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
