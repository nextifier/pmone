<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventProductCategoryRequest;
use App\Http\Requests\UpdateEventProductCategoryRequest;
use App\Http\Resources\EventProductCategoryResource;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventProductCategoryController extends Controller
{
    use AuthorizesRequests;

    private function resolveProject(string $username): Project
    {
        return Project::where('username', $username)->firstOrFail();
    }

    private function resolveEvent(Project $project, string $eventSlug): Event
    {
        return $project->events()->where('slug', $eventSlug)->firstOrFail();
    }

    public function index(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $query = $event->eventProductCategories()->with('media');

        if ($request->has('filter.search')) {
            $search = $request->input('filter.search');
            $query->where('title', 'ilike', "%{$search}%");
        }

        $query->ordered();

        $categories = $query->get();

        return response()->json([
            'data' => EventProductCategoryResource::collection($categories),
        ]);
    }

    public function store(StoreEventProductCategoryRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $category = $event->eventProductCategories()->create($request->validated());

        $this->handleTemporaryUploads($request, $category);
        $category->load('media');

        return response()->json([
            'message' => 'Product category created successfully',
            'data' => new EventProductCategoryResource($category),
        ], 201);
    }

    public function show(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $category = $event->eventProductCategories()->with(['media', 'products.media'])->findOrFail($id);

        return response()->json([
            'data' => new EventProductCategoryResource($category),
        ]);
    }

    public function update(UpdateEventProductCategoryRequest $request, string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $category = $event->eventProductCategories()->findOrFail($id);

        $category->update($request->validated());

        $this->handleTemporaryUploads($request, $category);
        $category->load('media');

        return response()->json([
            'message' => 'Product category updated successfully',
            'data' => new EventProductCategoryResource($category),
        ]);
    }

    public function destroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $category = $event->eventProductCategories()->findOrFail($id);

        $category->delete();

        return response()->json([
            'message' => 'Product category deleted successfully',
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:event_product_categories,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        foreach ($validated['orders'] as $orderData) {
            $cases[] = 'WHEN id = ? THEN ?::integer';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        \DB::statement(
            "UPDATE event_product_categories SET order_column = CASE {$casesString} END WHERE id IN ({$idsString}) AND event_id = ?",
            [...$params, $event->id]
        );

        return response()->json([
            'message' => 'Category order updated successfully',
        ]);
    }

    private function handleTemporaryUploads(Request $request, $model): void
    {
        $collections = ['catalog_files', 'description_images'];

        foreach ($collections as $collection) {
            $tmpField = 'tmp_'.$collection;
            $deleteField = 'delete_'.$collection;

            if ($request->has($deleteField) && $request->input($deleteField) === true) {
                $model->clearMediaCollection($collection);

                continue;
            }

            if (! $request->has($tmpField)) {
                continue;
            }

            $values = (array) $request->input($tmpField);

            foreach ($values as $value) {
                if (! $value || ! Str::startsWith($value, 'tmp-')) {
                    continue;
                }

                $metadataPath = "tmp/uploads/{$value}/metadata.json";

                if (! Storage::disk('local')->exists($metadataPath)) {
                    continue;
                }

                $metadata = json_decode(
                    Storage::disk('local')->get($metadataPath),
                    true
                );

                $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

                if (! Storage::disk('local')->exists($filePath)) {
                    continue;
                }

                $model->addMedia(Storage::disk('local')->path($filePath))
                    ->toMediaCollection($collection);

                Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
            }
        }
    }
}
