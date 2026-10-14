<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewStoreRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $reviews = Review::with('book')->latest()->paginate($perPage);

        return ReviewResource::collection($reviews);
    }

    public function show(Book $book): ReviewResource|JsonResponse
    {
        $review = $book->review;

        if (!$review) {
            return response()->json(['message' => 'Resenha não encontrada para este livro.'], 404);
        }

        return new ReviewResource($review->load('book'));
    }

    public function storeOrUpdate(ReviewStoreRequest $request, Book $book): ReviewResource
    {
        $review = Review::updateOrCreate(
            ['book_id' => $book->id],
            [
                'rating' => $request->rating,
                'title' => $request->title,
                'body' => $request->body,
                'has_spoiler' => $request->boolean('has_spoiler', false),
            ]
        );

        return new ReviewResource($review->load('book'));
    }

    public function destroy(Book $book): JsonResponse
    {
        $review = $book->review;

        if (!$review) {
            return response()->json(['message' => 'Resenha não encontrada.'], 404);
        }

        $review->delete();

        return response()->json(['message' => 'Resenha removida com sucesso.']);
    }
}
