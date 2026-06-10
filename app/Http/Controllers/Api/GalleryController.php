<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

class GalleryController extends Controller
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

    public function index(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        return response()->json([
            'data' => GalleryResource::collection($event->getMedia('gallery')),
            'meta' => ['total' => $event->getMedia('gallery')->count()],
        ]);
    }

    public function store(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', 'string'],
        ]);

        $added = 0;
        $failed = 0;
        foreach ($validated['files'] as $tmpFolder) {
            if ($this->attachTempImage($event, $tmpFolder)) {
                $added++;
            } else {
                $failed++;
            }
        }

        ResponseCache::clear(['gallery']);

        return response()->json([
            'message' => "{$added} image(s) uploaded",
            'added_count' => $added,
            'failed_count' => $failed,
            'data' => GalleryResource::collection($event->fresh()->getMedia('gallery')),
        ], 201);
    }

    private function attachTempImage(Event $event, string $tmpFolder): bool
    {
        if (! Str::startsWith($tmpFolder, 'tmp-')) {
            return false;
        }

        $metadataPath = "tmp/uploads/{$tmpFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return false;
        }

        try {
            $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
            $filePath = "tmp/uploads/{$tmpFolder}/{$metadata['original_name']}";

            if (! Storage::disk('local')->exists($filePath)) {
                return false;
            }

            $absolutePath = Storage::disk('local')->path($filePath);
            $dimensions = @getimagesize($absolutePath);

            // Unique disk file name: the gallery collection keeps every photo in
            // one folder, so two uploads sharing an original name would otherwise
            // overwrite each other on disk.
            $originalName = $metadata['original_name'] ?? basename($filePath);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'photo';
            $uniqueFileName = $base.'-'.Str::lower(Str::random(6)).($extension ? '.'.$extension : '');

            $media = $event->addMedia($absolutePath)
                ->usingFileName($uniqueFileName)
                ->toMediaCollection('gallery');

            if ($dimensions) {
                $media->setCustomProperty('width', $dimensions[0])
                    ->setCustomProperty('height', $dimensions[1])
                    ->save();
            }

            Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to attach gallery image from temp folder', [
                'event_id' => $event->id,
                'tmp_folder' => $tmpFolder,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
