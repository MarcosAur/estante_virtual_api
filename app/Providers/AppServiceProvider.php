<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'book' => Book::class,
            'movie' => Movie::class,
            'user' => \App\Models\User::class,
        ]);
    }
}
