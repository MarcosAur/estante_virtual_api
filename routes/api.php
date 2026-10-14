<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth / profile
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateProfile']);
        Route::delete('/me', [AuthController::class, 'deleteAccount']);

        // Books & Shelf
        Route::get('/books', [BookController::class, 'index']);
        Route::post('/books', [BookController::class, 'store']);
        Route::get('/books/{book}', [BookController::class, 'show']);
        Route::put('/books/{book}', [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);

        Route::patch('/books/{book}/progress', [BookController::class, 'updateProgress']);
        Route::post('/books/{book}/cover', [BookController::class, 'uploadCover']);
        Route::get('/books/{book}/cover', [BookController::class, 'getCover']);
        Route::delete('/books/{book}/cover', [BookController::class, 'deleteCover']);

        // Reviews for specific book
        Route::get('/books/{book}/review', [ReviewController::class, 'show']);
        Route::post('/books/{book}/review', [ReviewController::class, 'storeOrUpdate']);
        Route::put('/books/{book}/review', [ReviewController::class, 'storeOrUpdate']);
        Route::delete('/books/{book}/review', [ReviewController::class, 'destroy']);

        // Reviews global listing
        Route::get('/reviews', [ReviewController::class, 'index']);

        // Tags
        Route::get('/tags', [TagController::class, 'index']);
        Route::post('/tags', [TagController::class, 'store']);
        Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

        // Stats
        Route::get('/stats', [StatsController::class, 'index']);

        // External Lookup
        Route::get('/lookup/books', [LookupController::class, 'search']);
    });
});
