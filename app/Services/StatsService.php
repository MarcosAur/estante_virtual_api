<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Movie;
use App\Models\MovieReview;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class StatsService
{
    public function getStats(?int $year = null): array
    {
        $userId = Auth::id();
        $targetYear = $year ?: (int) date('Y');

        // Defense-in-depth: models have BelongsToUser global scope, and we can also query cleanly
        $allBooks = Book::all();
        $allReviews = Review::all();
        $allMovies = Movie::all();
        $allMovieReviews = MovieReview::all();

        // Books stats
        $booksReadThisYear = $allBooks->filter(function ($book) use ($targetYear) {
            if ($book->status !== 'read') {
                return false;
            }
            if ($book->finished_at) {
                return $book->finished_at->format('Y') == $targetYear;
            }
            return $book->updated_at?->format('Y') == $targetYear;
        });

        $pagesReadThisYear = $booksReadThisYear->sum('pages');
        $readCount = $booksReadThisYear->count();

        $avgRating = $allReviews->count() > 0 ? round($allReviews->avg('rating'), 1) : 0.0;

        $statusCounts = [
            'reading' => $allBooks->where('status', 'reading')->count(),
            'read' => $allBooks->where('status', 'read')->count(),
            'want_to_read' => $allBooks->where('status', 'want_to_read')->count(),
        ];

        $ratingsCount = [
            '5' => $allReviews->where('rating', '>=', 4.5)->count(),
            '4' => $allReviews->whereBetween('rating', [3.5, 4.4])->count(),
            '3' => $allReviews->whereBetween('rating', [2.5, 3.4])->count(),
            '2' => $allReviews->whereBetween('rating', [1.5, 2.4])->count(),
            '1' => $allReviews->where('rating', '<', 1.5)->count(),
        ];

        // Movies stats
        $moviesWatchedThisYear = $allMovies->filter(function ($movie) use ($targetYear) {
            if ($movie->status !== 'watched') {
                return false;
            }
            if ($movie->watched_at) {
                return $movie->watched_at->format('Y') == $targetYear;
            }
            return $movie->updated_at?->format('Y') == $targetYear;
        });

        $watchedCount = $moviesWatchedThisYear->count();
        $avgMovieRating = $allMovieReviews->count() > 0 ? round($allMovieReviews->avg('rating'), 1) : 0.0;

        $movieStatusCounts = [
            'watching' => $allMovies->where('status', 'watching')->count(),
            'watched' => $allMovies->where('status', 'watched')->count(),
            'want_to_watch' => $allMovies->where('status', 'want_to_watch')->count(),
        ];

        $movieRatingsCount = [
            '5' => $allMovieReviews->where('rating', '>=', 4.5)->count(),
            '4' => $allMovieReviews->whereBetween('rating', [3.5, 4.4])->count(),
            '3' => $allMovieReviews->whereBetween('rating', [2.5, 3.4])->count(),
            '2' => $allMovieReviews->whereBetween('rating', [1.5, 2.4])->count(),
            '1' => $allMovieReviews->where('rating', '<', 1.5)->count(),
        ];

        return [
            'year' => $targetYear,
            'total_books' => $allBooks->count(),
            'read_this_year' => $readCount,
            'pages_read_this_year' => (int) $pagesReadThisYear,
            'average_rating' => $avgRating,
            'reviews_count' => $allReviews->count(),
            'status_counts' => $statusCounts,
            'ratings_distribution' => $ratingsCount,

            // Movies
            'total_movies' => $allMovies->count(),
            'movies_watched_this_year' => $watchedCount,
            'movies_average_rating' => $avgMovieRating,
            'movies_reviews_count' => $allMovieReviews->count(),
            'movie_status_counts' => $movieStatusCounts,
            'movie_ratings_distribution' => $movieRatingsCount,
        ];
    }
}
