<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory, HasUlids, BelongsToUser;

    protected $fillable = [
        'name',
        'color',
    ];

    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_tag', 'tag_id', 'book_id')->withTimestamps();
    }
}
