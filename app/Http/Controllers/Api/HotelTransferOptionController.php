<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotelTransferOption\StoreHotelTransferOptionRequest;
use App\Http\Requests\HotelTransferOption\UpdateHotelTransferOptionRequest;
use App\Http\Resources\HotelTransferOptionResource;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelTransferOptionController extends Controller
{
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $query = $hotel->transferOptions();

        if ($direction = $request->input('filter_direction')) {
            $query->where('direction', $direction);
        }

        if ($request->has('filter_is_active') && $request->input('filter_is_active') !== '') {
            $query->where('is_active', $request->boolean('filter_is_active'));
        }

        $items = $query->orderBy('label')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => HotelTransferOptionResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Hotel $hotel, HotelTransferOption $transferOption): JsonResponse
    {
        abort_if($transferOption->hotel_id !== $hotel->id, 404);

        return response()->json(['data' => (new HotelTransferOptionResource($transferOption))->resolve()]);
    }

    public function store(StoreHotelTransferOptionRequest $request, Hotel $hotel): JsonResponse
    {
        $data = $request->validated();
        $data['hotel_id'] = $hotel->id;

        $option = HotelTransferOption::create($data);

        return response()->json([
            'data' => (new HotelTransferOptionResource($option))->resolve(),
            'message' => 'Transfer option created successfully',
        ], 201);
    }

    public function update(UpdateHotelTransferOptionRequest $request, Hotel $hotel, HotelTransferOption $transferOption): JsonResponse
    {
        abort_if($transferOption->hotel_id !== $hotel->id, 404);

        $transferOption->update($request->validated());

        return response()->json([
            'data' => (new HotelTransferOptionResource($transferOption))->resolve(),
            'message' => 'Transfer option updated successfully',
        ]);
    }

    public function destroy(Hotel $hotel, HotelTransferOption $transferOption): JsonResponse
    {
        abort_if($transferOption->hotel_id !== $hotel->id, 404);

        if (! auth()->user()?->can('hotels.update')) {
            abort(403);
        }

        $transferOption->delete();

        return response()->json(['message' => 'Transfer option deleted successfully']);
    }
}
