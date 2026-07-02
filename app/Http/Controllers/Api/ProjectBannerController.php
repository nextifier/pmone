<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectBannerRequest;
use App\Http\Requests\UpdateProjectBannerRequest;
use App\Http\Resources\ProjectBannerResource;
use App\Models\Project;
use App\Models\ProjectBanner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

class ProjectBannerController extends Controller
{
    use AuthorizesRequests;

    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $banners = $project->banners()
            ->ordered()
            ->with('media')
            ->withCount(['clicks', 'impressions'])
            ->get();

        return response()->json([
            'data' => ProjectBannerResource::collection($banners),
        ]);
    }

    public function store(StoreProjectBannerRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $data = $request->safe()->except(['tmp_image']);
            $data['sort_order'] = ($project->banners()->max('sort_order') ?? -1) + 1;

            $banner = $project->banners()->create($data);

            $tmpImage = $request->input('tmp_image');
            if ($tmpImage && Str::startsWith($tmpImage, 'tmp-')) {
                $this->moveTempToCollection($banner, $tmpImage, 'image');
            }

            $this->processContentImages($banner);

            ResponseCache::clear(['banners']);

            return response()->json([
                'message' => 'Banner created successfully',
                'data' => new ProjectBannerResource($banner->fresh('media')),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Project banner creation failed', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);

            return response()->json([
                'message' => 'Failed to create banner',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateProjectBannerRequest $request, Project $project, ProjectBanner $banner): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($banner->project_id === $project->id, 404);

        try {
            $banner->update($request->validated());

            $this->handleTemporaryUpload($request, $banner, 'tmp_image', 'image');

            $this->processContentImages($banner);

            ResponseCache::clear(['banners']);

            return response()->json([
                'message' => 'Banner updated successfully',
                'data' => new ProjectBannerResource($banner->fresh('media')),
            ]);
        } catch (\Exception $e) {
            logger()->error('Project banner update failed', [
                'error' => $e->getMessage(),
                'project_banner_id' => $banner->id,
            ]);

            return response()->json([
                'message' => 'Failed to update banner',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reorder(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        foreach ($validated['media_ids'] as $position => $id) {
            $cases[] = $isPostgres ? 'WHEN id = ? THEN ?::integer' : 'WHEN id = ? THEN ?';
            $params[] = $id;
            $params[] = $position;
            $ids[] = $id;
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        DB::statement(
            "UPDATE project_banners SET sort_order = CASE {$casesString} END WHERE id IN ({$idsString}) AND project_id = ?",
            [...$params, $project->id]
        );

        ResponseCache::clear(['banners']);

        return response()->json([
            'message' => 'Banner order updated successfully',
        ]);
    }

    public function bulkDelete(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer'],
        ]);

        $banners = $project->banners()->whereIn('id', $validated['media_ids'])->get();

        $deleted = [];
        foreach ($banners as $banner) {
            $id = $banner->id;
            $banner->delete();
            $deleted[] = ['id' => $id];
        }

        ResponseCache::clear(['banners']);

        return response()->json([
            'message' => 'Banners deleted successfully',
            'deleted_media' => $deleted,
        ]);
    }

    public function toggleActive(Project $project, ProjectBanner $banner): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($banner->project_id === $project->id, 404);

        $banner->update([
            'is_active' => ! $banner->is_active,
        ]);

        ResponseCache::clear(['banners']);

        return response()->json([
            'message' => 'Banner status updated successfully',
            'data' => new ProjectBannerResource($banner->fresh('media')),
        ]);
    }

    public function analytics(Request $request, Project $project, ProjectBanner $banner): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($banner->project_id === $project->id, 404);

        $days = max(1, min((int) $request->integer('days', 14), 365));
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $clicks = $banner->clicks()->whereBetween('clicked_at', [$start, $end]);
        $impressions = $banner->impressions()->whereBetween('visited_at', [$start, $end]);

        $totalClicks = (clone $clicks)->count();
        $totalImpressions = (clone $impressions)->count();
        $ctr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;

        $clicksByDay = (clone $clicks)
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');
        $impressionsByDay = (clone $impressions)
            ->selectRaw('DATE(visited_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $perDay = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $perDay[] = [
                'date' => $date,
                'impressions' => (int) ($impressionsByDay[$date] ?? 0),
                'clicks' => (int) ($clicksByDay[$date] ?? 0),
            ];
            $cursor->addDay();
        }

        $recentClicks = $banner->clicks()
            ->latest('clicked_at')
            ->limit(10)
            ->get()
            ->map(fn ($click) => [
                'id' => $click->id,
                'clicked_at' => $click->clicked_at?->toISOString(),
                'referer' => $click->referer,
            ]);

        return response()->json([
            'data' => [
                'summary' => [
                    'impressions' => $totalImpressions,
                    'clicks' => $totalClicks,
                    'ctr' => $ctr,
                ],
                'per_day' => $perDay,
                'recent_clicks' => $recentClicks,
            ],
        ]);
    }

    private function handleTemporaryUpload(Request $request, ProjectBanner $model, string $fieldName, string $collection): void
    {
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->boolean($deleteFieldName, false)) {
            $model->clearMediaCollection($collection);

            return;
        }

        if (! $request->has($fieldName)) {
            return;
        }

        $value = $request->input($fieldName);

        if (! $value || ! Str::startsWith($value, 'tmp-')) {
            return;
        }

        $this->moveTempToCollection($model, $value, $collection);
    }

    private function moveTempToCollection(ProjectBanner $model, string $tmpFolder, string $collection): void
    {
        $metadataPath = "tmp/uploads/{$tmpFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(
            Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$tmpFolder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $model->clearMediaCollection($collection);

        $absolutePath = Storage::disk('local')->path($filePath);

        $customProperties = [];
        $imageInfo = @getimagesize($absolutePath);
        if ($imageInfo !== false) {
            $customProperties['width'] = $imageInfo[0];
            $customProperties['height'] = $imageInfo[1];
        }

        $model->addMedia($absolutePath)
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");
    }

    /**
     * Move TipTap temp content images (description) into permanent media and
     * rewrite their URLs. Mirrors EventController::processContentImages.
     */
    private function processContentImages(ProjectBanner $banner): void
    {
        if (! $banner->description) {
            return;
        }

        $content = $banner->description;
        $pattern = '/<img[^>]+src="(?:https?:\/\/[^\/]+)?\/api\/tmp-media\/(tmp-media-[a-zA-Z0-9._-]+)"[^>]*>/';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $fullImgTag = $match[0];
            $folder = $match[1];

            try {
                $metadataPath = "tmp/uploads/{$folder}/metadata.json";

                if (! Storage::disk('local')->exists($metadataPath)) {
                    continue;
                }

                $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
                $filename = $metadata['original_name'];
                $tempFilePath = "tmp/uploads/{$folder}/{$filename}";

                if (! Storage::disk('local')->exists($tempFilePath)) {
                    continue;
                }

                $caption = null;
                if (preg_match('/data-caption="([^"]*)"/', $fullImgTag, $captionMatch)) {
                    $caption = html_entity_decode($captionMatch[1]);
                }

                $baseName = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) ?: 'image';
                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                $mediaAdder = $banner->addMediaFromDisk($tempFilePath, 'local')
                    ->usingName($baseName)
                    ->usingFileName($baseName.($extension ? '.'.$extension : ''));

                if ($caption) {
                    $mediaAdder->withCustomProperties(['caption' => $caption]);
                }

                $media = $mediaAdder->toMediaCollection('description_images');

                $responsiveImg = $this->buildResponsiveImageHtml($media, $caption);
                $content = str_replace($fullImgTag, $responsiveImg, $content);

                Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");
            } catch (\Exception $e) {
                logger()->warning('Failed to process banner content image', [
                    'folder' => $folder,
                    'error' => $e->getMessage(),
                    'project_banner_id' => $banner->id,
                ]);
            }
        }

        if ($content !== $banner->description) {
            $banner->update(['description' => $content]);
        }
    }

    private function buildResponsiveImageHtml($media, ?string $caption = null): string
    {
        $alt = $caption ?? $media->getCustomProperty('caption') ?? $media->name;

        $srcset = [
            $media->getUrl('sm').' 600w',
            $media->getUrl('md').' 900w',
            $media->getUrl('lg').' 1200w',
            $media->getUrl('xl').' 1600w',
        ];

        $srcsetString = implode(', ', $srcset);
        $sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 1200px';

        $captionAttr = $caption
            ? sprintf(' data-caption="%s"', htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'))
            : '';

        return sprintf(
            '<img src="%s" srcset="%s" sizes="%s" alt="%s"%s loading="lazy" class="w-full h-auto rounded-lg">',
            $media->getUrl('lg'),
            $srcsetString,
            $sizes,
            htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'),
            $captionAttr
        );
    }
}
