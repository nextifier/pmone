<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Allotment\StoreAllotmentRequest;
use App\Http\Requests\Allotment\UpdateAllotmentRequest;
use App\Http\Resources\AllotmentResource;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllotmentController extends Controller
{
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $query = $hotel->allotments()->with(['event', 'roomType']);

        if ($eventId = $request->input('filter_event_id')) {
            $query->where('event_id', $eventId);
        }

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

    public function show(Hotel $hotel, HotelEventAllotment $allotment): JsonResponse
    {
        abort_if($allotment->hotel_id !== $hotel->id, 404);

        $allotment->load(['event', 'roomType', 'hotel']);

        return response()->json(['data' => (new AllotmentResource($allotment))->resolve()]);
    }

    public function store(StoreAllotmentRequest $request, Hotel $hotel): JsonResponse
    {
        $data = $request->validated();
        $data['hotel_id'] = $hotel->id;

        $allotment = HotelEventAllotment::create($data);
        $allotment->load(['event', 'roomType']);

        return response()->json([
            'data' => (new AllotmentResource($allotment))->resolve(),
            'message' => 'Allotment created successfully',
        ], 201);
    }

    public function update(UpdateAllotmentRequest $request, Hotel $hotel, HotelEventAllotment $allotment): JsonResponse
    {
        abort_if($allotment->hotel_id !== $hotel->id, 404);

        $allotment->update($request->validated());
        $allotment->load(['event', 'roomType']);

        return response()->json([
            'data' => (new AllotmentResource($allotment))->resolve(),
            'message' => 'Allotment updated successfully',
        ]);
    }

    public function destroy(Hotel $hotel, HotelEventAllotment $allotment): JsonResponse
    {
        abort_if($allotment->hotel_id !== $hotel->id, 404);

        if (! auth()->user()?->can('allotments.delete')) {
            abort(403);
        }

        $allotment->delete();

        return response()->json(['message' => 'Allotment deleted successfully']);
    }
}
