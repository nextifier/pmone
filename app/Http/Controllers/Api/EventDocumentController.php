<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventDocumentRequest;
use App\Http\Requests\UpdateEventDocumentRequest;
use App\Http\Resources\EventDocumentResource;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventDocumentController extends Controller
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

        $query = $event->eventDocuments()->with('media');

        if ($request->has('filter.search')) {
            $search = $request->input('filter.search');
            $query->where('title', 'ilike', "%{$search}%");
        }

        if ($request->has('filter.document_type')) {
            $query->where('document_type', $request->input('filter.document_type'));
        }

        $query->ordered();

        $documents = $query->get();

        return response()->json([
            'data' => EventDocumentResource::collection($documents),
        ]);
    }

    public function store(StoreEventDocumentRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $document = $event->eventDocuments()->create($request->validated());

        $this->handleTemporaryUploads($request, $document);
        $document->load('media');

        return response()->json([
            'message' => 'Document created successfully',
            'data' => new EventDocumentResource($document),
        ], 201);
    }

    public function show(string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $document = $event->eventDocuments()->with('media')->where('ulid', $ulid)->firstOrFail();

        return response()->json([
            'data' => new EventDocumentResource($document),
        ]);
    }

    public function update(UpdateEventDocumentRequest $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $document = $event->eventDocuments()->where('ulid', $ulid)->firstOrFail();

        $shouldIncrementVersion = $request->boolean('increment_version', false);

        $document->update($request->validated());

        if ($shouldIncrementVersion) {
            $document->incrementContentVersion();
        }

        $this->handleTemporaryUploads($request, $document);
        $document->load('media');

        return response()->json([
            'message' => 'Document updated successfully',
            'data' => new EventDocumentResource($document),
        ]);
    }

    public function destroy(string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $document = $event->eventDocuments()->where('ulid', $ulid)->firstOrFail();

        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully',
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:event_documents,id'],
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
            "UPDATE event_documents SET order_column = CASE {$casesString} END WHERE id IN ({$idsString}) AND event_id = ?",
            [...$params, $event->id]
        );

        return response()->json([
            'message' => 'Document order updated successfully',
        ]);
    }

    private function handleTemporaryUploads(Request $request, $model): void
    {
        $collections = ['template_en', 'template_id', 'example_file', 'description_images'];

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

            $value = $request->input($tmpField);

            if (is_array($value)) {
                foreach ($value as $v) {
                    $this->processTemporaryFile($model, $collection, $v);
                }
            } else {
                $this->processTemporaryFile($model, $collection, $value);
            }
        }
    }

    private function processTemporaryFile($model, string $collection, ?string $value): void
    {
        if (! $value || ! Str::startsWith($value, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$value}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(
            Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        // Single file collections: clear before adding
        $singleFileCollections = ['template_en', 'template_id', 'example_file'];
        if (in_array($collection, $singleFileCollections)) {
            $model->clearMediaCollection($collection);
        }

        $model->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }
}
