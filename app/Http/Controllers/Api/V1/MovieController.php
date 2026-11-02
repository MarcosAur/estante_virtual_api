<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieStatusRequest;
use App\Http\Requests\MovieStoreRequest;
use App\Http\Requests\MovieUpdateRequest;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MovieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Movie::with(['tags', 'review']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('director', 'like', "%{$q}%")
                    ->orWhere('genre', 'like', "%{$q}%");
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
            case 'director':
                $query->orderBy('director', 'asc');
                break;
            case 'runtime':
                $query->orderBy('runtime_minutes', 'desc');
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
        $movies = $query->paginate($perPage);

        return MovieResource::collection($movies);
    }

    public function store(MovieStoreRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['tag_ids', 'poster', 'poster_url']);

        if (($data['status'] ?? null) === 'watched' && empty($data['watched_at'])) {
            $data['watched_at'] = now()->toDateString();
        }

        $movie = Movie::create($data);

        if ($request->hasFile('poster')) {
            $path = $request->file('poster')->store("private/posters/{$movie->user_id}", 'local');
            $movie->update(['poster_path' => $path]);
        } elseif ($request->filled('poster_url')) {
            $this->downloadAndSavePoster($movie, $request->input('poster_url'));
        }

        if ($request->has('tag_ids') && is_array($request->tag_ids)) {
            $movie->tags()->sync($request->tag_ids);
        }

        return (new MovieResource($movie->load(['tags', 'review'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Movie $movie): MovieResource
    {
        return new MovieResource($movie->load(['tags', 'review']));
    }

    public function update(MovieUpdateRequest $request, Movie $movie): MovieResource
    {
        $data = $request->safe()->except(['tag_ids', 'poster', 'poster_url']);

        if (($data['status'] ?? null) === 'watched' && empty($data['watched_at']) && empty($movie->watched_at)) {
            $data['watched_at'] = now()->toDateString();
        }

        $movie->update($data);

        if ($request->hasFile('poster')) {
            if ($movie->poster_path && Storage::disk('local')->exists($movie->poster_path)) {
                Storage::disk('local')->delete($movie->poster_path);
            }
            $path = $request->file('poster')->store("private/posters/{$movie->user_id}", 'local');
            $movie->update(['poster_path' => $path]);
        } elseif ($request->filled('poster_url')) {
            $this->downloadAndSavePoster($movie, $request->input('poster_url'));
        }

        if ($request->has('tag_ids')) {
            $movie->tags()->sync($request->tag_ids ?: []);
        }

        return new MovieResource($movie->fresh(['tags', 'review']));
    }

    public function destroy(Movie $movie): JsonResponse
    {
        $movie->delete();

        return response()->json(['message' => 'Filme removido com sucesso.']);
    }

    public function updateStatus(MovieStatusRequest $request, Movie $movie): MovieResource
    {
        $status = $request->status;
        $watchedAt = $request->watched_at ?: $movie->watched_at;

        if ($status === 'watched' && !$watchedAt) {
            $watchedAt = now()->toDateString();
        }

        $movie->update([
            'status' => $status,
            'watched_at' => $watchedAt,
        ]);

        return new MovieResource($movie->fresh(['tags', 'review']));
    }

    public function uploadPoster(Request $request, Movie $movie): MovieResource
    {
        $request->validate([
            'poster' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ]);

        if ($movie->poster_path && Storage::disk('local')->exists($movie->poster_path)) {
            Storage::disk('local')->delete($movie->poster_path);
        }

        $path = $request->file('poster')->store("private/posters/{$movie->user_id}", 'local');
        $movie->update(['poster_path' => $path]);

        return new MovieResource($movie->fresh(['tags', 'review']));
    }

    public function deletePoster(Movie $movie): MovieResource
    {
        if ($movie->poster_path && Storage::disk('local')->exists($movie->poster_path)) {
            Storage::disk('local')->delete($movie->poster_path);
        }

        $movie->update(['poster_path' => null]);

        return new MovieResource($movie->fresh(['tags', 'review']));
    }

    public function getPoster(Movie $movie)
    {
        if (!$movie->poster_path || !Storage::disk('local')->exists($movie->poster_path)) {
            abort(404, 'Pôster não encontrado.');
        }

        return Storage::disk('local')->response($movie->poster_path);
    }

    private function downloadAndSavePoster(Movie $movie, string $url): void
    {
        try {
            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                return;
            }

            $response = Http::timeout(5)->get($url);
            if ($response->successful()) {
                if ($movie->poster_path && Storage::disk('local')->exists($movie->poster_path)) {
                    Storage::disk('local')->delete($movie->poster_path);
                }

                $filename = 'poster_' . Str::random(20) . '.jpg';
                $path = "private/posters/{$movie->user_id}/{$filename}";
                Storage::disk('local')->put($path, $response->body());
                $movie->update(['poster_path' => $path]);
            }
        } catch (\Throwable) {
            // Ignore download failure on poster external
        }
    }
}
