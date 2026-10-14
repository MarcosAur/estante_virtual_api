<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagStoreRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tags = Tag::withCount('books')->orderBy('name')->get();

        return TagResource::collection($tags);
    }

    public function store(TagStoreRequest $request): JsonResponse
    {
        $tag = Tag::create($request->validated());

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(201);
    }

    public function update(TagStoreRequest $request, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return new TagResource($tag);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(['message' => 'Tag removida com sucesso.']);
    }
}
