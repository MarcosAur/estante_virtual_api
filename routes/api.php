<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\MovieController;
use App\Http\Controllers\Api\V1\MovieReviewController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\SuggestionController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\WritingEntryController;
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

        // Movies & Shelf
        Route::get('/movies', [MovieController::class, 'index']);
        Route::post('/movies', [MovieController::class, 'store']);
        Route::get('/movies/{movie}', [MovieController::class, 'show']);
        Route::put('/movies/{movie}', [MovieController::class, 'update']);
        Route::delete('/movies/{movie}', [MovieController::class, 'destroy']);

        Route::patch('/movies/{movie}/status', [MovieController::class, 'updateStatus']);
        Route::post('/movies/{movie}/poster', [MovieController::class, 'uploadPoster']);
        Route::get('/movies/{movie}/poster', [MovieController::class, 'getPoster']);
        Route::delete('/movies/{movie}/poster', [MovieController::class, 'deletePoster']);

        // Reviews for specific movie
        Route::get('/movies/{movie}/review', [MovieReviewController::class, 'show']);
        Route::post('/movies/{movie}/review', [MovieReviewController::class, 'storeOrUpdate']);
        Route::put('/movies/{movie}/review', [MovieReviewController::class, 'storeOrUpdate']);
        Route::delete('/movies/{movie}/review', [MovieReviewController::class, 'destroy']);

        // Movie Reviews global listing
        Route::get('/movie-reviews', [MovieReviewController::class, 'index']);

        // Tags
        Route::get('/tags', [TagController::class, 'index']);
        Route::post('/tags', [TagController::class, 'store']);
        Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

        // Stats
        Route::get('/stats', [StatsController::class, 'index']);

        // AI Suggestions
        Route::get('/suggestions', [SuggestionController::class, 'showLatest']);
        Route::post('/suggestions', [SuggestionController::class, 'generate'])->middleware('throttle:10,1');
        Route::post('/suggestions/{id}/import', [SuggestionController::class, 'batchImport']);
        Route::post('/suggested-media/{id}/import', [SuggestionController::class, 'importItem']);

        // Writings
        Route::get('/writings/summary', [WritingEntryController::class, 'summary']);
        Route::get('/writings', [WritingEntryController::class, 'index']);
        Route::post('/writings', [WritingEntryController::class, 'store']);
        Route::get('/writings/{writing}', [WritingEntryController::class, 'show']);
        Route::put('/writings/{writing}', [WritingEntryController::class, 'update']);
        Route::delete('/writings/{writing}', [WritingEntryController::class, 'destroy']);
    });
});
