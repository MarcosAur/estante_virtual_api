<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookProgressRequest;
use App\Http\Requests\BookStoreRequest;
use App\Http\Requests\BookUpdateRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Book::with(['tags', 'review']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('author', 'like', "%{$q}%")
                    ->orWhere('isbn', 'like', "%{$q}%");
            });
        }

        if ($request->filled('tag')) {
            $tag = $request->query('tag');
            $query->whereHas('tags', function ($sub) use ($tag) {
                $sub->where('tags.id', $tag)
                    ->orWhere('tags.name', $tag);
            });
        }

        if ($request->filled('rating')) {
            $minRating = (float) $request->query('rating');
            $query->whereHas('review', function ($sub) use ($minRating) {
                $sub->where('rating', '>=', $minRating);
            });
        }

        $sort = $request->query('sort', 'recent');
        switch ($sort) {
            case 'title_asc':
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'author':
                $query->orderBy('author', 'asc');
                break;
            case 'pages':
                $query->orderBy('pages', 'desc');
                break;
            case 'year':
                $query->orderBy('year', 'desc');
                break;
            case 'recent':
            default:
                $query->latest();
                break;
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 24)));
        $books = $query->paginate($perPage);

        return BookResource::collection($books);
    }

    public function store(BookStoreRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['tag_ids', 'cover']);
        $book = Book::create($data);

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store("private/covers/{$book->user_id}", 'local');
            $book->update(['cover_path' => $path]);
        }

        if ($request->has('tag_ids') && is_array($request->tag_ids)) {
            $book->tags()->sync($request->tag_ids);
        }

        return (new BookResource($book->load(['tags', 'review'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Book $book): BookResource
    {
        return new BookResource($book->load(['tags', 'review']));
    }

    public function update(BookUpdateRequest $request, Book $book): BookResource
    {
        $data = $request->safe()->except(['tag_ids', 'cover']);
        $book->update($data);

        if ($request->hasFile('cover')) {
            if ($book->cover_path && Storage::disk('local')->exists($book->cover_path)) {
                Storage::disk('local')->delete($book->cover_path);
            }
            $path = $request->file('cover')->store("private/covers/{$book->user_id}", 'local');
            $book->update(['cover_path' => $path]);
        }

        if ($request->has('tag_ids')) {
            $book->tags()->sync($request->tag_ids ?: []);
        }

        return new BookResource($book->fresh(['tags', 'review']));
    }

    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json(['message' => 'Livro removido com sucesso.']);
    }

    public function updateProgress(BookProgressRequest $request, Book $book): BookResource
    {
        $currentPage = (int) $request->current_page;
        $status = $request->status ?: $book->status;
        $finishedAt = $request->finished_at ?: $book->finished_at;

        if ($book->pages > 0 && $currentPage >= $book->pages) {
            $status = 'read';
            if (!$finishedAt) {
                $finishedAt = now()->toDateString();
            }
        }

        if ($status === 'reading' && !$book->started_at) {
            $book->started_at = now()->toDateString();
        }

        $book->update([
            'current_page' => $currentPage,
            'status' => $status,
            'finished_at' => $finishedAt,
            'started_at' => $book->started_at,
        ]);

        return new BookResource($book->fresh(['tags', 'review']));
    }

    public function uploadCover(Request $request, Book $book): BookResource
    {
        $request->validate([
            'cover' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ]);

        if ($book->cover_path && Storage::disk('local')->exists($book->cover_path)) {
            Storage::disk('local')->delete($book->cover_path);
        }

        $path = $request->file('cover')->store("private/covers/{$book->user_id}", 'local');
        $book->update(['cover_path' => $path]);

        return new BookResource($book->fresh(['tags', 'review']));
    }

    public function deleteCover(Book $book): BookResource
    {
        if ($book->cover_path && Storage::disk('local')->exists($book->cover_path)) {
            Storage::disk('local')->delete($book->cover_path);
        }

        $book->update(['cover_path' => null]);

        return new BookResource($book->fresh(['tags', 'review']));
    }

    public function getCover(Book $book)
    {
        if (!$book->cover_path || !Storage::disk('local')->exists($book->cover_path)) {
            abort(404, 'Capa não encontrada.');
        }

        return Storage::disk('local')->response($book->cover_path);
    }
}
