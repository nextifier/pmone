<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Jobs\BulkDeleteMedia;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Update gallery display settings for an event. Merges into the event's
     * `settings` JSON so other settings keys are preserved. The aspect ratio
     * drives how the public event website crops each gallery tile.
     */
    public function updateSettings(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'aspect_ratio' => ['required', 'string', 'regex:/^[1-9]\d{0,2}:[1-9]\d{0,2}$/'],
        ]);

        $settings = $event->settings ?? [];
        $settings['gallery_aspect_ratio'] = $validated['aspect_ratio'];
        $event->update(['settings' => $settings]);

        ResponseCache::clear(['gallery']);

        return response()->json([
            'message' => 'Gallery settings updated',
            'data' => ['aspect_ratio' => $validated['aspect_ratio']],
        ]);
    }

    /**
     * Queue a bulk delete of gallery photos and return a job id the client can
     * poll for progress. Used for large selections so the request returns
     * immediately; small selections keep using the synchronous generic media
     * endpoint. Ids are scoped to this event's gallery collection.
     */
    public function bulkDelete(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer'],
        ]);

        $ids = $event->getMedia('gallery')
            ->whereIn('id', $validated['media_ids'])
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return response()->json(['message' => 'No matching gallery photos.'], 422);
        }

        $jobId = Str::uuid()->toString();

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => count($ids),
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Preparing to delete photos...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkDeleteMedia::dispatch($jobId, $ids, auth()->id(), ['gallery']);

        return response()->json(['job_id' => $jobId]);
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

            // The gallery collection keeps every photo in one folder; the global
            // UniqueFileNamer appends a random token to the stored file name so two
            // uploads sharing an original name never overwrite each other on disk.
            $originalName = $metadata['original_name'] ?? basename($filePath);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'photo';

            $media = $event->addMedia($absolutePath)
                ->usingFileName($base.($extension ? '.'.$extension : ''))
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
