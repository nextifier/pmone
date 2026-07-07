<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomType\StoreRoomTypeRequest;
use App\Http\Requests\RoomType\UpdateRoomTypeRequest;
use App\Http\Resources\RoomTypeResource;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\RoomTypePricingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;

class RoomTypeController extends Controller
{
    public function index(Request $request, Event $event, Hotel $hotel): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);

        $query = $hotel->roomTypes()->with(['media', 'pricingPeriods']);

        if ($search = $request->input('filter_search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($request->has('filter_is_active') && $request->input('filter_is_active') !== '') {
            $query->where('is_active', $request->boolean('filter_is_active'));
        }

        $roomTypes = $query->orderBy('name')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => RoomTypeResource::collection($roomTypes)->resolve(),
            'meta' => [
                'current_page' => $roomTypes->currentPage(),
                'last_page' => $roomTypes->lastPage(),
                'per_page' => $roomTypes->perPage(),
                'total' => $roomTypes->total(),
            ],
        ]);
    }

    public function show(Event $event, Hotel $hotel, RoomType $roomType): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);
        abort_if($roomType->hotel_id !== $hotel->id, 404);

        $roomType->load(['media', 'hotel', 'pricingPeriods']);

        return response()->json(['data' => (new RoomTypeResource($roomType))->resolve()]);
    }

    public function store(StoreRoomTypeRequest $request, Event $event, Hotel $hotel): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);

        $data = $request->safe()->except(['gallery_files', 'amenities', 'pricing_periods']);
        $data['hotel_id'] = $hotel->id;

        $roomType = DB::transaction(function () use ($data, $request) {
            $roomType = RoomType::create($data);

            if ($request->has('pricing_periods')) {
                $this->syncPricingPeriods($roomType, $request->input('pricing_periods', []));
            }

            return $roomType;
        });

        $this->syncAmenities($roomType, $request->input('amenities'));
        $this->handleGalleryUpload($request, $roomType);

        // The trait clear fired on create, BEFORE amenities/media were written;
        // clear again so no request re-caches the half-built payload.
        ResponseCache::clear(['hotels']);

        $roomType->load(['media', 'tags', 'pricingPeriods']);

        return response()->json([
            'data' => (new RoomTypeResource($roomType))->resolve(),
            'message' => 'Room type created successfully',
        ], 201);
    }

    public function update(UpdateRoomTypeRequest $request, Event $event, Hotel $hotel, RoomType $roomType): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);
        abort_if($roomType->hotel_id !== $hotel->id, 404);

        $data = $request->safe()->except(['gallery_files', 'amenities', 'pricing_periods']);

        DB::transaction(function () use ($roomType, $data, $request) {
            $roomType->update($data);

            if ($request->has('pricing_periods')) {
                $this->syncPricingPeriods($roomType, $request->input('pricing_periods', []));
            }
        });

        if ($request->has('amenities')) {
            $this->syncAmenities($roomType, $request->input('amenities'));
        }

        $this->handleGalleryUpload($request, $roomType);

        // The trait clear fired on $roomType->update(), BEFORE the pricing
        // periods (query-builder update), amenities and media were written.
        ResponseCache::clear(['hotels']);

        $roomType->load(['media', 'tags', 'pricingPeriods']);

        return response()->json([
            'data' => (new RoomTypeResource($roomType))->resolve(),
            'message' => 'Room type updated successfully',
        ]);
    }

    private function syncPricingPeriods(RoomType $roomType, array $periods): void
    {
        $submittedIds = collect($periods)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $roomType->pricingPeriods()
            ->whereNotIn('id', $submittedIds)
            ->get()
            ->each(fn (RoomTypePricingPeriod $p) => $p->delete());

        foreach ($periods as $period) {
            $payload = [
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
                'rate' => $period['rate'],
                'label' => $period['label'] ?? null,
                'is_active' => array_key_exists('is_active', $period)
                    ? (bool) $period['is_active']
                    : true,
            ];

            if (! empty($period['id'])) {
                $roomType->pricingPeriods()
                    ->whereKey($period['id'])
                    ->update($payload);
            } else {
                $roomType->pricingPeriods()->create($payload);
            }
        }
    }

    private function syncAmenities(RoomType $roomType, ?array $amenities): void
    {
        $roomType->syncTagsWithType($amenities ?? [], 'room_amenity');
    }

    public function destroy(Event $event, Hotel $hotel, RoomType $roomType): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);
        abort_if($roomType->hotel_id !== $hotel->id, 404);

        if (! auth()->user()?->can('room_types.delete')) {
            abort(403);
        }

        $roomType->delete();

        return response()->json(['message' => 'Room type deleted successfully']);
    }

    public function reorderMedia(Request $request, Event $event, Hotel $hotel, RoomType $roomType): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);
        abort_if($roomType->hotel_id !== $hotel->id, 404);

        $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer'],
        ]);

        $ids = $request->input('media_ids');
        $existing = $roomType->getMedia('gallery')->pluck('id')->toArray();
        $valid = array_intersect($ids, $existing);

        if (count($valid) !== count($existing)) {
            return response()->json(['message' => 'Media id mismatch'], 422);
        }

        Media::setNewOrder($valid);

        ResponseCache::clear(['hotels']);

        return response()->json(['message' => 'Order updated']);
    }

    private function ensureHotelBelongsToEvent(Event $event, Hotel $hotel): void
    {
        $exists = DB::table('hotel_event')
            ->where('event_id', $event->id)
            ->where('hotel_id', $hotel->id)
            ->exists();

        abort_if(! $exists, 404, 'Hotel not attached to this event.');
    }

    private function handleGalleryUpload(Request $request, RoomType $roomType): void
    {
        $files = $request->input('gallery_files', []);

        if (! is_array($files) || empty($files)) {
            return;
        }

        foreach ($files as $tmpFolder) {
            if (! is_string($tmpFolder) || ! Str::startsWith($tmpFolder, 'tmp-')) {
                continue;
            }

            $metadataPath = "tmp/uploads/{$tmpFolder}/metadata.json";

            if (! Storage::disk('local')->exists($metadataPath)) {
                continue;
            }

            $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
            $filePath = "tmp/uploads/{$tmpFolder}/{$metadata['original_name']}";

            if (! Storage::disk('local')->exists($filePath)) {
                continue;
            }

            $roomType->addMedia(Storage::disk('local')->path($filePath))
                ->toMediaCollection('gallery');

            Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");
        }
    }
}
