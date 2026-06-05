<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkPageBannerRequest;
use App\Http\Requests\UpdateLinkPageBannerRequest;
use App\Http\Resources\LinkPageBannerResource;
use App\Models\LinkPage;
use App\Models\LinkPageBanner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

class LinkPageBannerController extends Controller
{
    use AuthorizesRequests;

    public function index(LinkPage $linkPage): JsonResponse
    {
        $this->authorize('view', $linkPage);

        $banners = $linkPage->banners()->ordered()->with('media')->get();

        return response()->json([
            'data' => LinkPageBannerResource::collection($banners),
        ]);
    }

    public function store(StoreLinkPageBannerRequest $request, LinkPage $linkPage): JsonResponse
    {
        $this->authorize('update', $linkPage);

        try {
            $tmpImages = $request->validated()['tmp_images'];
            $nextOrder = ($linkPage->banners()->max('sort_order') ?? -1) + 1;
            $createdIds = [];

            foreach ($tmpImages as $tmpFolder) {
                if (! is_string($tmpFolder) || ! Str::startsWith($tmpFolder, 'tmp-')) {
                    continue;
                }

                $banner = $linkPage->banners()->create([
                    'sort_order' => $nextOrder++,
                    'is_active' => true,
                ]);

                $this->moveTempToCollection($banner, $tmpFolder, 'image');
                $createdIds[] = $banner->id;
            }

            $banners = LinkPageBanner::whereIn('id', $createdIds)->ordered()->with('media')->get();

            ResponseCache::clear(['short-links']);

            return response()->json([
                'message' => 'Banners created successfully',
                'data' => LinkPageBannerResource::collection($banners),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Link page banner creation failed', [
                'error' => $e->getMessage(),
                'link_page_id' => $linkPage->id,
            ]);

            return response()->json([
                'message' => 'Failed to create banners',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateLinkPageBannerRequest $request, LinkPage $linkPage, LinkPageBanner $linkPageBanner): JsonResponse
    {
        $this->authorize('update', $linkPage);

        try {
            $linkPageBanner->update($request->validated());

            $this->handleTemporaryUpload($request, $linkPageBanner, 'tmp_image', 'image');

            ResponseCache::clear(['short-links']);

            return response()->json([
                'message' => 'Banner updated successfully',
                'data' => new LinkPageBannerResource($linkPageBanner->fresh()),
            ]);
        } catch (\Exception $e) {
            logger()->error('Link page banner update failed', [
                'error' => $e->getMessage(),
                'link_page_banner_id' => $linkPageBanner->id,
            ]);

            return response()->json([
                'message' => 'Failed to update banner',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reorder(Request $request, LinkPage $linkPage): JsonResponse
    {
        $this->authorize('update', $linkPage);

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
            "UPDATE link_page_banners SET sort_order = CASE {$casesString} END WHERE id IN ({$idsString}) AND link_page_id = ?",
            [...$params, $linkPage->id]
        );

        ResponseCache::clear(['short-links']);

        return response()->json([
            'message' => 'Banner order updated successfully',
        ]);
    }

    public function bulkDelete(Request $request, LinkPage $linkPage): JsonResponse
    {
        $this->authorize('update', $linkPage);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'integer'],
        ]);

        $banners = $linkPage->banners()->whereIn('id', $validated['media_ids'])->get();

        $deleted = [];
        foreach ($banners as $banner) {
            $id = $banner->id;
            $banner->delete();
            $deleted[] = ['id' => $id];
        }

        ResponseCache::clear(['short-links']);

        return response()->json([
            'message' => 'Banners deleted successfully',
            'deleted_media' => $deleted,
        ]);
    }

    public function toggleActive(LinkPage $linkPage, LinkPageBanner $linkPageBanner): JsonResponse
    {
        $this->authorize('update', $linkPage);

        $linkPageBanner->update([
            'is_active' => ! $linkPageBanner->is_active,
        ]);

        ResponseCache::clear(['short-links']);

        return response()->json([
            'message' => 'Banner status updated successfully',
            'data' => new LinkPageBannerResource($linkPageBanner->fresh()),
        ]);
    }

    private function handleTemporaryUpload(Request $request, LinkPageBanner $model, string $fieldName, string $collection): void
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

    private function moveTempToCollection(LinkPageBanner $model, string $tmpFolder, string $collection): void
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

        $model->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");
    }
}
