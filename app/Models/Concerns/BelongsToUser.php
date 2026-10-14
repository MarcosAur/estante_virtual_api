<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owned_by_user', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', Auth::id());
            } else {
                // If not authenticated (and not running in console without request), deny all records to guarantee strict isolation
                if (!app()->runningInConsole()) {
                    $builder->whereRaw('1 = 0');
                }
            }
        });

        static::creating(function ($model) {
            if (Auth::check() && empty($model->user_id)) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
