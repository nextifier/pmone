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
        $query = Hotel::query()
            ->active()
            ->with([
                'event:id,slug,title,project_id,start_date,end_date,is_active',
                'event.project:id,username,name',
                'media',
                'roomTypes' => fn ($q) => $q->active(),
            ])
            ->whereHas('event', function ($q) use ($request) {
                $includeInactive = $request->boolean('include_inactive');

                if (! $includeInactive) {
                    $q->where('is_active', true);
                }

                if ($eventSlug = $request->input('event_slug')) {
                    $q->where('slug', $eventSlug);
                }

                if ($projectSlug = $request->input('project_slug')) {
                    $q->whereHas('project', fn ($p) => $p->where('username', $projectSlug));
                }
            });

        $hotels = $query->get();

        return response()->json([
            'data' => PublicHotelResource::collection($hotels)->resolve(),
        ]);
    }

    public function show(string $eventSlug, string $hotelSlug): JsonResponse
    {
        $hotel = Hotel::query()
            ->active()
            ->where('slug', $hotelSlug)
            ->whereHas('event', fn ($q) => $q->where('slug', $eventSlug)->where('is_active', true))
            ->with([
                'event:id,slug,title,project_id,start_date,end_date,is_active',
                'event.project:id,username,name',
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

        $hotel = Hotel::find($data['hotel_id']);
        $roomType = RoomType::find($data['room_type_id']);

        $available = $this->reservations->checkAvailability(
            $hotel?->event_id,
            $data['hotel_id'],
            $data['room_type_id'],
            $data['check_in_date'],
            $data['check_out_date'],
        );

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
