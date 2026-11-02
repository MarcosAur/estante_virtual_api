<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WritingEntry extends Model
{
    use HasFactory, SoftDeletes, HasUlids, BelongsToUser;

    /**
     * user_id is deliberately omitted to enforce automatic auth assignment and isolation
     */
    protected $fillable = [
        'kind',
        'title',
        'body',
        'entry_date',
        'source_label',
        'mood',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date:Y-m-d',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_writing_entry', 'writing_entry_id', 'tag_id')->withTimestamps();
    }
}
