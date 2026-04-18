<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hotel\StoreHotelRequest;
use App\Http\Requests\Hotel\UpdateHotelRequest;
use App\Http\Resources\HotelIndexResource;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HotelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Hotel::query()
            ->with(['media'])
            ->withCount(['roomTypes', 'reservations']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = (int) $request->input('per_page', 15);
        $hotels = $query->paginate($perPage);

        return response()->json([
            'data' => HotelIndexResource::collection($hotels)->resolve(),
            'meta' => [
                'current_page' => $hotels->currentPage(),
                'last_page' => $hotels->lastPage(),
                'per_page' => $hotels->perPage(),
                'total' => $hotels->total(),
            ],
        ]);
    }

    public function show(Hotel $hotel): JsonResponse
    {
        $hotel->load(['media', 'creator', 'updater'])
            ->loadCount(['roomTypes', 'reservations']);

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
        ]);
    }

    public function store(StoreHotelRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['tmp_featured', 'gallery_files']);

        $hotel = Hotel::create($data);

        $this->handleFeaturedUpload($request, $hotel);
        $this->handleGalleryUpload($request, $hotel);

        $hotel->load('media');

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
            'message' => 'Hotel created successfully',
        ], 201);
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel): JsonResponse
    {
        $data = $request->safe()->except(['tmp_featured', 'delete_featured', 'gallery_files']);

        $hotel->update($data);

        $this->handleFeaturedUpload($request, $hotel);
        $this->handleGalleryUpload($request, $hotel);

        $hotel->load('media');

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
            'message' => 'Hotel updated successfully',
        ]);
    }

    public function destroy(Hotel $hotel): JsonResponse
    {
        $this->authorizeAction('hotels.delete');

        $hotel->delete();

        return response()->json(['message' => 'Hotel deleted successfully']);
    }

    public function reorderMedia(Request $request, Hotel $hotel, string $collection): JsonResponse
    {
        $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer'],
        ]);

        if (! in_array($collection, ['gallery'], true)) {
            return response()->json(['message' => 'Invalid collection'], 422);
        }

        $ids = $request->input('media_ids');
        $existing = $hotel->getMedia($collection)->pluck('id')->toArray();

        $valid = array_intersect($ids, $existing);

        if (count($valid) !== count($existing)) {
            return response()->json(['message' => 'Media id mismatch'], 422);
        }

        Media::setNewOrder($valid);

        return response()->json(['message' => 'Order updated']);
    }

    public function trash(Request $request): JsonResponse
    {
        $query = Hotel::onlyTrashed()
            ->with(['media', 'deleter']);

        $this->applyFilters($query, $request);
        $query->orderBy('deleted_at', 'desc');

        $hotels = $query->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => HotelIndexResource::collection($hotels)->resolve(),
            'meta' => [
                'current_page' => $hotels->currentPage(),
                'last_page' => $hotels->lastPage(),
                'per_page' => $hotels->perPage(),
                'total' => $hotels->total(),
            ],
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $hotel = Hotel::onlyTrashed()->findOrFail($id);
        $hotel->restore();

        return response()->json(['message' => 'Hotel restored successfully']);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $hotel = Hotel::onlyTrashed()->findOrFail($id);
        $hotel->forceDelete();

        return response()->json(['message' => 'Hotel permanently deleted']);
    }

    private function authorizeAction(string $permission): void
    {
        if (! auth()->user()?->can($permission)) {
            abort(403);
        }
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->input('filter_search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('city', 'ilike', "%{$search}%");
            });
        }

        if ($city = $request->input('filter_city')) {
            $cities = is_array($city) ? $city : explode(',', $city);
            $query->whereIn('city', array_filter($cities));
        }

        if ($request->has('filter_is_active') && $request->input('filter_is_active') !== '') {
            $query->where('is_active', $request->boolean('filter_is_active'));
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', 'name');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $fieldMap = [
            'name' => 'name',
            'city' => 'city',
            'created_at' => 'created_at',
            'commission_rate' => 'commission_rate',
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }

    private function handleFeaturedUpload(Request $request, Hotel $hotel): void
    {
        if ($request->boolean('delete_featured')) {
            $hotel->clearMediaCollection('featured');

            return;
        }

        $tmpFolder = $request->input('tmp_featured');

        if (! $tmpFolder || ! Str::startsWith($tmpFolder, 'tmp-')) {
            return;
        }

        $this->moveTempToCollection($hotel, $tmpFolder, 'featured', clearFirst: true);
    }

    private function handleGalleryUpload(Request $request, Hotel $hotel): void
    {
        $files = $request->input('gallery_files', []);

        if (! is_array($files) || empty($files)) {
            return;
        }

        foreach ($files as $tmpFolder) {
            if (! is_string($tmpFolder) || ! Str::startsWith($tmpFolder, 'tmp-')) {
                continue;
            }

            $this->moveTempToCollection($hotel, $tmpFolder, 'gallery', clearFirst: false);
        }
    }

    private function moveTempToCollection(Hotel $hotel, string $tmpFolder, string $collection, bool $clearFirst): void
    {
        $metadataPath = "tmp/uploads/{$tmpFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpFolder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        if ($clearFirst) {
            $hotel->clearMediaCollection($collection);
        }

        $hotel->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");
    }
}
