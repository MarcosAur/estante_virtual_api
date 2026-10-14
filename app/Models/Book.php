<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes, HasUlids, BelongsToUser;

    /**
     * user_id is deliberately omitted to enforce automatic auth assignment and isolation
     */
    protected $fillable = [
        'title',
        'author',
        'isbn',
        'publisher',
        'year',
        'pages',
        'cover_path',
        'synopsis',
        'status',
        'current_page',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'pages' => 'integer',
            'current_page' => 'integer',
            'started_at' => 'date:Y-m-d',
            'finished_at' => 'date:Y-m-d',
        ];
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'book_tag', 'book_id', 'tag_id')->withTimestamps();
    }
}
