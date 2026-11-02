<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovieReview extends Model
{
    use HasFactory, SoftDeletes, HasUlids, BelongsToUser;

    /**
     * user_id is deliberately omitted to enforce automatic auth assignment and isolation
     */
    protected $fillable = [
        'movie_id',
        'rating',
        'title',
        'body',
        'has_spoiler',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'has_spoiler' => 'boolean',
        ];
    }

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }
}
