<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentRequest;
use App\Http\Requests\UpdateContentRequest;
use App\Http\Resources\ContentResource;
use App\Models\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $contents = Content::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('content_type'), fn ($query) => $query->where('content_type', $request->input('content_type')))
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(ContentResource::collection($contents));
    }

    public function store(StoreContentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['content_type'] = $data['content_type'] ?? Content::TYPE_NOTE;
        $data['status'] = $data['status'] ?? Content::STATUS_DRAFT;

        $content = Content::query()->create($data);

        return $this->successResponse(new ContentResource($content), 'success', 201);
    }

    public function show(int $id): JsonResponse
    {
        $content = Content::query()->findOrFail($id);

        return $this->successResponse(new ContentResource($content));
    }

    public function update(UpdateContentRequest $request, int $id): JsonResponse
    {
        $content = Content::query()->findOrFail($id);
        $content->update($request->validated());
        $content->refresh();

        return $this->successResponse(new ContentResource($content));
    }

    public function destroy(int $id): JsonResponse
    {
        $content = Content::query()->findOrFail($id);
        $content->delete();

        return $this->successResponse(null, 'success', 204);
    }
}
