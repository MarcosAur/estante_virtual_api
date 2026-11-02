<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Movie extends Model
{
    use HasFactory, SoftDeletes, HasUlids, BelongsToUser;

    /**
     * user_id is deliberately omitted to enforce automatic auth assignment and isolation
     */
    protected $fillable = [
        'title',
        'director',
        'year',
        'runtime_minutes',
        'genre',
        'synopsis',
        'poster_path',
        'tmdb_id',
        'status',
        'watched_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'runtime_minutes' => 'integer',
            'watched_at' => 'date:Y-m-d',
        ];
    }

    public function review()
    {
        return $this->hasOne(MovieReview::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'movie_tag', 'movie_id', 'tag_id')->withTimestamps();
    }
}
