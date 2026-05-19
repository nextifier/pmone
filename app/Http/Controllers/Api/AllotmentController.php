<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Allotment\StoreAllotmentRequest;
use App\Http\Requests\Allotment\UpdateAllotmentRequest;
use App\Http\Resources\AllotmentResource;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AllotmentController extends Controller
{
    public function index(Request $request, Event $event, Hotel $hotel): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);

        $query = $hotel->allotments()->with(['roomType']);

        if ($roomTypeId = $request->input('filter_room_type_id')) {
            $query->where('room_type_id', $roomTypeId);
        }

        if ($request->has('filter_is_active') && $request->input('filter_is_active') !== '') {
            $query->where('is_active', $request->boolean('filter_is_active'));
        }

        $allotments = $query->orderByDesc('start_date')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => AllotmentResource::collection($allotments)->resolve(),
            'meta' => [
                'current_page' => $allotments->currentPage(),
                'last_page' => $allotments->lastPage(),
                'per_page' => $allotments->perPage(),
                'total' => $allotments->total(),
            ],
        ]);
    }

    public function show(Event $event, Hotel $hotel, HotelEventAllotment $allotment): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);
        abort_if($allotment->hotel_id !== $hotel->id, 404);

        $allotment->load(['roomType', 'hotel']);

        return response()->json(['data' => (new AllotmentResource($allotment))->resolve()]);
    }

    public function store(StoreAllotmentRequest $request, Event $event, Hotel $hotel): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);

        $data = $request->validated();
        $data['hotel_id'] = $hotel->id;

        $allotment = HotelEventAllotment::create($data);
        $allotment->load(['roomType']);

        return response()->json([
            'data' => (new AllotmentResource($allotment))->resolve(),
            'message' => 'Allotment created successfully',
        ], 201);
    }

    public function update(UpdateAllotmentRequest $request, Event $event, Hotel $hotel, HotelEventAllotment $allotment): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);
        abort_if($allotment->hotel_id !== $hotel->id, 404);

        $allotment->update($request->validated());
        $allotment->load(['roomType']);

        return response()->json([
            'data' => (new AllotmentResource($allotment))->resolve(),
            'message' => 'Allotment updated successfully',
        ]);
    }

    public function destroy(Event $event, Hotel $hotel, HotelEventAllotment $allotment): JsonResponse
    {
        $this->ensureHotelBelongsToEvent($event, $hotel);
        abort_if($allotment->hotel_id !== $hotel->id, 404);

        if (! auth()->user()?->can('allotments.delete')) {
            abort(403);
        }

        $allotment->delete();

        return response()->json(['message' => 'Allotment deleted successfully']);
    }

    private function ensureHotelBelongsToEvent(Event $event, Hotel $hotel): void
    {
        $exists = DB::table('hotel_event')
            ->where('event_id', $event->id)
            ->where('hotel_id', $hotel->id)
            ->exists();

        abort_if(! $exists, 404, 'Hotel not attached to this event.');
    }
}
