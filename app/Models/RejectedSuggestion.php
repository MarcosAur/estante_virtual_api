<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectedSuggestion extends Model
{
    use HasFactory, HasUlids, BelongsToUser;

    protected $table = 'rejected_suggestions';

    protected $fillable = [
        'type',
        'title',
        'normalized_title',
    ];
}
