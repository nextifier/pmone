<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicReservation\CheckAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Http\Resources\PublicHotelResource;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\Hotel\AllotmentService;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PublicHotelController extends Controller
{
    public function __construct(
        protected AllotmentService $allotments,
        protected ReservationService $reservations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $eventId = $request->input('event_id');
        $checkIn = $request->input('check_in_date');
        $checkOut = $request->input('check_out_date');

        if ($eventId && $checkIn && $checkOut) {
            $hotels = $this->allotments->getAvailableHotelsForEvent((int) $eventId, $checkIn, $checkOut);
        } else {
            $hotels = Hotel::query()
                ->active()
                ->with(['media', 'roomTypes' => fn ($q) => $q->active()])
                ->get();
        }

        return response()->json([
            'data' => PublicHotelResource::collection($hotels)->resolve(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $hotel = Hotel::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'media',
                'roomTypes' => fn ($q) => $q->active()->with('media'),
                'transferOptions' => fn ($q) => $q->active(),
            ])
            ->firstOrFail();

        $hotel->roomTypes->each(fn ($r) => $r->setRelation('hotel', $hotel));

        return response()->json([
            'data' => (new PublicHotelResource($hotel))->resolve(),
        ]);
    }

    public function availability(CheckAvailabilityRequest $request): JsonResponse
    {
        $data = $request->validated();

        $available = $this->reservations->checkAvailability(
            $data['event_id'] ?? null,
            $data['hotel_id'],
            $data['room_type_id'],
            $data['check_in_date'],
            $data['check_out_date'],
        );

        $hotel = Hotel::find($data['hotel_id']);
        $roomType = RoomType::find($data['room_type_id']);

        $base = (float) $roomType->base_rate;
        $taxRate = (float) ($hotel->tax_percentage ?? 0) / 100;
        $serviceRate = (float) ($hotel->service_charge_percentage ?? 0) / 100;
        $allInPerNight = round($base * (1 + $taxRate + $serviceRate), 2);

        $nights = max(1, Carbon::parse($data['check_in_date'])
            ->diffInDays(Carbon::parse($data['check_out_date'])));

        $estimatedTotal = $allInPerNight * $nights * (int) $data['qty'];

        return response()->json([
            'data' => (new AvailabilityResource([
                'available' => $available,
                'qty' => $data['qty'],
                'rate_per_night' => $base,
                'all_in_per_night' => $allInPerNight,
                'estimated_total' => $estimatedTotal,
            ]))->resolve(),
        ]);
    }
}
