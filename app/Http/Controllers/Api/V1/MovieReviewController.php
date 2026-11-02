<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieReviewStoreRequest;
use App\Http\Resources\MovieReviewResource;
use App\Models\Movie;
use App\Models\MovieReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MovieReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $reviews = MovieReview::with('movie')->latest()->paginate($perPage);

        return MovieReviewResource::collection($reviews);
    }

    public function show(Movie $movie): MovieReviewResource|JsonResponse
    {
        $review = $movie->review;

        if (!$review) {
            return response()->json(['message' => 'Resenha não encontrada para este filme.'], 404);
        }

        return new MovieReviewResource($review->load('movie'));
    }

    public function storeOrUpdate(MovieReviewStoreRequest $request, Movie $movie): MovieReviewResource
    {
        $review = MovieReview::updateOrCreate(
            ['movie_id' => $movie->id],
            [
                'rating' => $request->rating,
                'title' => $request->title,
                'body' => $request->body,
                'has_spoiler' => $request->boolean('has_spoiler', false),
            ]
        );

        return new MovieReviewResource($review->load('movie'));
    }

    public function destroy(Movie $movie): JsonResponse
    {
        $review = $movie->review;

        if (!$review) {
            return response()->json(['message' => 'Resenha não encontrada.'], 404);
        }

        $review->delete();

        return response()->json(['message' => 'Resenha removida com sucesso.']);
    }
}
