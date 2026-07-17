<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkPageItemRequest;
use App\Http\Requests\UpdateLinkPageItemRequest;
use App\Http\Resources\LinkPageItemResource;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

class LinkPageItemController extends Controller
{
    use AuthorizesRequests;

    public function index(LinkPage $linkPage): JsonResponse
    {
        $this->authorize('view', $linkPage);

        $items = $linkPage->items()->ordered()->get();

        return response()->json([
            'data' => LinkPageItemResource::collection($items),
        ]);
    }

    public function store(StoreLinkPageItemRequest $request, LinkPage $linkPage): JsonResponse
    {
        $this->authorize('update', $linkPage);

        try {
            $data = $request->validated();

            // Auto-set sort_order if not provided
            if (! isset($data['sort_order'])) {
                $data['sort_order'] = $linkPage->items()->max('sort_order') + 1;
            }

            $item = $linkPage->items()->create($data);

            $this->handleTemporaryUpload($request, $item, 'tmp_poster', 'poster');

            // The trait clear from create() fired BEFORE the poster write, so
            // clear again after it. Mirrors LinkPageBannerController.
            ResponseCache::clear(['short-links']);

            return response()->json([
                'message' => 'Item created successfully',
                'data' => new LinkPageItemResource($item),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Link page item creation failed', [
                'error' => $e->getMessage(),
                'link_page_id' => $linkPage->id,
            ]);

            return response()->json([
                'message' => 'Failed to create item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateLinkPageItemRequest $request, LinkPage $linkPage, LinkPageItem $linkPageItem): JsonResponse
    {
        $this->authorize('update', $linkPage);

        try {
            $linkPageItem->update($request->validated());

            $this->handleTemporaryUpload($request, $linkPageItem, 'tmp_poster', 'poster');

            // The trait clear from update() fired BEFORE the poster write, so
            // clear again after it. Mirrors LinkPageBannerController.
            ResponseCache::clear(['short-links']);

            return response()->json([
                'message' => 'Item updated successfully',
                'data' => new LinkPageItemResource($linkPageItem->fresh()),
            ]);
        } catch (\Exception $e) {
            logger()->error('Link page item update failed', [
                'error' => $e->getMessage(),
                'link_page_item_id' => $linkPageItem->id,
            ]);

            return response()->json([
                'message' => 'Failed to update item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(LinkPage $linkPage, LinkPageItem $linkPageItem): JsonResponse
    {
        $this->authorize('update', $linkPage);

        $linkPageItem->delete();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }

    public function reorder(Request $request, LinkPage $linkPage): JsonResponse
    {
        $this->authorize('update', $linkPage);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:link_page_items,id'],
            'orders.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        foreach ($validated['orders'] as $orderData) {
            $cases[] = $isPostgres ? 'WHEN id = ? THEN ?::integer' : 'WHEN id = ? THEN ?';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        DB::statement(
            "UPDATE link_page_items SET sort_order = CASE {$casesString} END WHERE id IN ({$idsString}) AND link_page_id = ?",
            [...$params, $linkPage->id]
        );

        ResponseCache::clear(['short-links']);

        return response()->json([
            'message' => 'Item order updated successfully',
        ]);
    }

    public function trash(LinkPage $linkPage): JsonResponse
    {
        $this->authorize('view', $linkPage);

        $items = $linkPage->items()->onlyTrashed()->ordered()->get();

        return response()->json([
            'data' => LinkPageItemResource::collection($items),
        ]);
    }

    public function restore(LinkPage $linkPage, int $id): JsonResponse
    {
        $this->authorize('update', $linkPage);

        $item = $linkPage->items()->onlyTrashed()->findOrFail($id);
        $item->restore();

        return response()->json([
            'message' => 'Item restored successfully',
            'data' => new LinkPageItemResource($item),
        ]);
    }

    public function forceDestroy(LinkPage $linkPage, int $id): JsonResponse
    {
        $this->authorize('update', $linkPage);

        $item = $linkPage->items()->onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Item permanently deleted',
        ]);
    }

    public function toggleActive(LinkPage $linkPage, LinkPageItem $linkPageItem): JsonResponse
    {
        $this->authorize('update', $linkPage);

        $linkPageItem->update([
            'is_active' => ! $linkPageItem->is_active,
        ]);

        return response()->json([
            'message' => 'Item status updated successfully',
            'data' => new LinkPageItemResource($linkPageItem->fresh()),
        ]);
    }

    private function handleTemporaryUpload(Request $request, LinkPageItem $model, string $fieldName, string $collection): void
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

        $model->clearMediaCollection($collection);

        $model->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }
}
