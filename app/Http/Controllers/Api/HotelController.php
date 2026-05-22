<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hotel\StoreHotelRequest;
use App\Http\Requests\Hotel\UpdateHotelRequest;
use App\Http\Resources\HotelIndexResource;
use App\Http\Resources\HotelResource;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HotelController extends Controller
{
    // ─── Event-scoped endpoints (pivot-aware) ──────────────────────────

    public function index(Request $request, Event $event): JsonResponse
    {
        $query = $event->hotels()
            ->with(['media'])
            ->withCount(['roomTypes']);

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

    public function show(Event $event, Hotel $hotel): JsonResponse
    {
        $this->ensureHotelAttachedToEvent($event, $hotel);

        $hotel->load(['media', 'creator', 'updater', 'events' => fn ($q) => $q->where('events.id', $event->id)])
            ->loadCount(['roomTypes']);

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
        ]);
    }

    /**
     * Two modes:
     *  - body has `hotel_id` → attach existing hotel to event
     *  - body has hotel fields without `hotel_id` → create global hotel + attach
     */
    public function store(StoreHotelRequest $request, Event $event): JsonResponse
    {
        $hotelId = $request->input('hotel_id');
        $pivotData = $request->input('pivot', ['is_active' => true]);

        if ($hotelId) {
            $hotel = Hotel::query()->findOrFail($hotelId);
        } else {
            $data = $request->safe()->except(['tmp_featured', 'gallery_files', 'facilities', 'hotel_id', 'pivot']);
            $hotel = Hotel::create($data);
            $this->syncFacilities($hotel, $request->input('facilities'));
            $this->handleFeaturedUpload($request, $hotel);
            $this->handleGalleryUpload($request, $hotel);
        }

        HotelEvent::firstOrCreate(
            ['hotel_id' => $hotel->id, 'event_id' => $event->id],
            array_intersect_key(
                array_merge(['is_active' => true], $pivotData),
                array_flip(['is_active', 'notes', 'order_column']),
            ),
        );

        $hotel->load(['media', 'tags', 'events' => fn ($q) => $q->where('events.id', $event->id)]);

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
            'message' => $hotelId ? 'Hotel attached to event' : 'Hotel created and attached',
        ], 201);
    }

    public function update(UpdateHotelRequest $request, Event $event, Hotel $hotel): JsonResponse
    {
        $this->ensureHotelAttachedToEvent($event, $hotel);

        $hotelData = $request->safe()->except(['tmp_featured', 'delete_featured', 'gallery_files', 'facilities', 'pivot']);
        if (! empty($hotelData)) {
            $hotel->update($hotelData);
        }

        if ($pivot = $request->input('pivot')) {
            HotelEvent::query()
                ->where(['hotel_id' => $hotel->id, 'event_id' => $event->id])
                ->update(array_intersect_key($pivot, array_flip(['is_active', 'notes', 'order_column'])));
        }

        if ($request->has('facilities')) {
            $this->syncFacilities($hotel, $request->input('facilities'));
        }

        $this->handleFeaturedUpload($request, $hotel);
        $this->handleGalleryUpload($request, $hotel);

        $hotel->load(['media', 'tags', 'events' => fn ($q) => $q->where('events.id', $event->id)]);

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
            'message' => 'Hotel updated successfully',
        ]);
    }

    /**
     * Detach hotel from event - hard delete pivot row, hotel record itself stays.
     */
    public function destroy(Event $event, Hotel $hotel): JsonResponse
    {
        $this->ensureHotelAttachedToEvent($event, $hotel);

        HotelEvent::query()
            ->where(['hotel_id' => $hotel->id, 'event_id' => $event->id])
            ->delete();

        return response()->json(['message' => 'Hotel detached from event']);
    }

    public function reorderMedia(Request $request, Event $event, Hotel $hotel, string $collection): JsonResponse
    {
        $this->ensureHotelAttachedToEvent($event, $hotel);

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

    // ─── Global hotel master endpoints ──────────────────────────────────

    public function globalIndex(Request $request): JsonResponse
    {
        $this->authorizeAction('hotels.read');

        $query = Hotel::query()
            ->with([
                'media',
                'events:id,slug,title,project_id',
                'events.project:id,username,name',
            ])
            ->withCount(['roomTypes', 'events']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

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

    public function globalStore(StoreHotelRequest $request): JsonResponse
    {
        $this->authorizeAction('hotels.create');

        $data = $request->safe()->except(['tmp_featured', 'gallery_files', 'facilities', 'hotel_id', 'pivot']);
        $hotel = Hotel::create($data);

        $this->syncFacilities($hotel, $request->input('facilities'));
        $this->handleFeaturedUpload($request, $hotel);
        $this->handleGalleryUpload($request, $hotel);

        $hotel->load(['media', 'tags']);

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
            'message' => 'Hotel created successfully',
        ], 201);
    }

    public function globalShow(Hotel $hotel): JsonResponse
    {
        $this->authorizeAction('hotels.read');

        $hotel->load([
            'media',
            'tags',
            'creator',
            'updater',
            'events' => fn ($q) => $q->orderByPivot('order_column'),
            'events.project:id,username,name',
            'roomTypes' => fn ($q) => $q->withTrashed()->orderBy('order_column'),
            'roomTypes.media',
        ])->loadCount(['roomTypes', 'events']);

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
        ]);
    }

    public function globalUpdate(UpdateHotelRequest $request, Hotel $hotel): JsonResponse
    {
        $this->authorizeAction('hotels.update');

        $data = $request->safe()->except(['tmp_featured', 'delete_featured', 'gallery_files', 'facilities', 'pivot']);
        if (! empty($data)) {
            $hotel->update($data);
        }

        if ($request->has('facilities')) {
            $this->syncFacilities($hotel, $request->input('facilities'));
        }

        $this->handleFeaturedUpload($request, $hotel);
        $this->handleGalleryUpload($request, $hotel);

        $hotel->load(['media', 'tags']);

        return response()->json([
            'data' => (new HotelResource($hotel))->resolve(),
            'message' => 'Hotel updated successfully',
        ]);
    }

    /**
     * Soft delete hotel globally. Cascades pivot rows via foreign key.
     */
    public function globalDestroy(Hotel $hotel): JsonResponse
    {
        $this->authorizeAction('hotels.delete');

        $hotel->delete();

        return response()->json(['message' => 'Hotel deleted globally']);
    }

    public function globalTrash(Request $request): JsonResponse
    {
        $this->authorizeAction('hotels.delete');

        $query = Hotel::onlyTrashed()->with(['media', 'deleter']);
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

    public function globalRestore(int $id): JsonResponse
    {
        $this->authorizeAction('hotels.delete');

        $hotel = Hotel::onlyTrashed()->findOrFail($id);
        $hotel->restore();

        return response()->json(['message' => 'Hotel restored successfully']);
    }

    public function globalForceDestroy(int $id): JsonResponse
    {
        $this->authorizeAction('hotels.delete');

        $hotel = Hotel::onlyTrashed()->findOrFail($id);
        $hotel->forceDelete();

        return response()->json(['message' => 'Hotel permanently deleted']);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function ensureHotelAttachedToEvent(Event $event, Hotel $hotel): void
    {
        $exists = DB::table('hotel_event')
            ->where('event_id', $event->id)
            ->where('hotel_id', $hotel->id)
            ->exists();

        if (! $exists) {
            throw new NotFoundHttpException('Hotel is not attached to this event.');
        }
    }

    private function syncFacilities(Hotel $hotel, ?array $facilities): void
    {
        $hotel->syncTagsWithType($facilities ?? [], 'hotel_facility');
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
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('name', $likeOperator, "%{$search}%")
                    ->orWhereRaw("address->>'city' {$likeOperator} ?", ["%{$search}%"]);
            });
        }

        if ($city = $request->input('filter_city')) {
            $cities = array_filter(is_array($city) ? $city : explode(',', $city));
            if (! empty($cities)) {
                $query->where(function ($q) use ($cities) {
                    foreach ($cities as $c) {
                        $q->orWhereRaw("address->>'city' = ?", [$c]);
                    }
                });
            }
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

        if ($field === 'city') {
            $query->orderByRaw("address->>'city' {$direction}");

            return;
        }

        $fieldMap = [
            'name' => 'name',
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
