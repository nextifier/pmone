<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\PricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicReservation\CheckAvailabilityRequest;
use App\Http\Requests\PublicReservation\DailyAvailabilityAggregateRequest;
use App\Http\Requests\PublicReservation\DailyAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Http\Resources\PublicHotelResource;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\RoomType;
use App\Services\Hotel\AllotmentService;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PublicHotelController extends Controller
{
    public function __construct(
        protected AllotmentService $allotments,
        protected ReservationService $reservations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');
        $eventSlug = $request->input('event_slug');
        $projectSlug = $request->input('project_slug');

        $query = Hotel::query()
            ->active()
            ->with([
                'events' => function ($q) use ($eventSlug, $projectSlug, $includeInactive) {
                    if (! $includeInactive) {
                        $q->where('events.is_active', true)
                            ->where('events.hotel_reservation_enabled', true)
                            ->where('hotel_event.is_active', true)
                            ->whereHas('project.paymentGateways', fn ($p) => $p->where('is_active', true));
                    }
                    if ($eventSlug) {
                        $q->where('events.slug', $eventSlug);
                    }
                    if ($projectSlug) {
                        $q->whereHas('project', fn ($p) => $p->where('username', $projectSlug));
                    }
                },
                'events.project:id,username,name',
                'events.project.paymentGateways',
                'events.media',
                'media',
                'tags',
                'roomTypes' => fn ($q) => $q->active()->with([
                    'tags',
                    'pricingPeriods' => fn ($p) => $p->active()->orderBy('start_date'),
                ]),
                'transferOptions' => fn ($q) => $q->active(),
            ])
            ->whereHas('events', function ($q) use ($eventSlug, $projectSlug, $includeInactive) {
                if (! $includeInactive) {
                    $q->where('events.is_active', true)
                        ->where('events.hotel_reservation_enabled', true)
                        ->where('hotel_event.is_active', true)
                        ->whereHas('project.paymentGateways', fn ($p) => $p->where('is_active', true));
                }
                if ($eventSlug) {
                    $q->where('events.slug', $eventSlug);
                }
                if ($projectSlug) {
                    $q->whereHas('project', fn ($p) => $p->where('username', $projectSlug));
                }
            });

        $hotels = $query->get();

        // SQL `is_active=true` is a necessary pre-filter; `isConfigured()` is
        // the source of truth (validates decrypted secret_key shape). Apply
        // both — and prune events whose project fails the configured check
        // so the response never advertises an unbookable hotel/event.
        if (! $includeInactive) {
            $hotels = $hotels
                ->map(function (Hotel $hotel) {
                    $hotel->setRelation(
                        'events',
                        $hotel->events->filter(
                            fn ($event) => $event->project?->hasActivePaymentGateway() === true,
                        )->values(),
                    );

                    return $hotel;
                })
                ->filter(fn (Hotel $hotel) => $hotel->events->isNotEmpty())
                ->values();
        }

        return response()->json([
            'data' => PublicHotelResource::collection($hotels)->resolve(),
        ]);
    }

    public function show(string $eventSlug, string $hotelSlug): JsonResponse
    {
        $hotel = Hotel::query()
            ->active()
            ->where('slug', $hotelSlug)
            ->whereHas('events', fn ($q) => $q->where('events.slug', $eventSlug)
                ->where('events.is_active', true)
                ->where('hotel_event.is_active', true))
            ->with([
                'events' => fn ($q) => $q->where('events.slug', $eventSlug),
                'events.project:id,username,name',
                'media',
                'tags',
                'roomTypes' => fn ($q) => $q->active()->with([
                    'media',
                    'tags',
                    'pricingPeriods' => fn ($p) => $p->active()->orderBy('start_date'),
                ]),
                'transferOptions' => fn ($q) => $q->active(),
            ])
            ->firstOrFail();

        $hotel->roomTypes->each(fn ($r) => $r->setRelation('hotel', $hotel));

        return response()->json([
            'data' => (new PublicHotelResource($hotel))->resolve(),
        ]);
    }

    public function dailyAvailability(
        DailyAvailabilityRequest $request,
        string $eventSlug,
        string $hotelSlug,
        int $roomTypeId,
    ): JsonResponse {
        $hotel = $this->resolveHotelForEventSlug($eventSlug, $hotelSlug);

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->where('hotel_id', $hotel->id)
            ->active()
            ->with('pricingPeriods')
            ->firstOrFail();

        $data = $request->validated();
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        return response()->json([
            'data' => $this->reservations->dailyAvailability($hotel, $roomType, $start, $end),
        ]);
    }

    public function dailyAvailabilityAggregate(
        DailyAvailabilityAggregateRequest $request,
        string $eventSlug,
        string $hotelSlug,
    ): JsonResponse {
        $hotel = $this->resolveHotelForEventSlug($eventSlug, $hotelSlug);

        $data = $request->validated();
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        return response()->json([
            'data' => $this->reservations->aggregateDailyAvailability($hotel, $start, $end),
        ]);
    }

    public function availability(CheckAvailabilityRequest $request): JsonResponse
    {
        $data = $request->validated();

        $event = Event::query()->where('slug', $data['event_slug'])->firstOrFail();
        $hotel = Hotel::query()->findOrFail($data['hotel_id']);
        $this->ensureHotelAttachedToEvent($event, $hotel);

        $roomType = RoomType::with('pricingPeriods')->find($data['room_type_id']);

        $available = $this->reservations->checkAvailability(
            $event->id,
            $data['hotel_id'],
            $data['room_type_id'],
            $data['check_in_date'],
            $data['check_out_date'],
        );

        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);
        $qty = (int) $data['qty'];

        $allotment = HotelEventAllotment::query()
            ->active()
            ->where('hotel_id', $hotel->id)
            ->where('room_type_id', $roomType->id)
            ->whereDate('start_date', '<=', $checkIn->toDateString())
            ->whereDate('end_date', '>=', $checkOut->toDateString())
            ->orderBy('id')
            ->first();

        $preview = $this->reservations->previewSubtotal($roomType, $checkIn, $checkOut, $qty, $allotment);

        $taxRate = (float) ($hotel->tax_percentage ?? 0) / 100;
        $serviceRate = (float) ($hotel->service_charge_percentage ?? 0) / 100;
        $allInPerNight = round($preview['rate_per_night_avg'] * (1 + $taxRate + $serviceRate), 2);
        $estimatedTotal = round($preview['subtotal'] * (1 + $taxRate + $serviceRate), 2);

        return response()->json([
            'data' => (new AvailabilityResource([
                'available' => $available,
                'qty' => $qty,
                'rate_per_night' => $preview['rate_per_night_avg'],
                'all_in_per_night' => $allInPerNight,
                'subtotal' => $preview['subtotal'],
                'estimated_total' => $estimatedTotal,
                'pricing_type' => $roomType->pricing_type instanceof PricingType
                    ? $roomType->pricing_type->value
                    : (string) $roomType->pricing_type,
                'daily_breakdown' => $preview['daily_breakdown'],
            ]))->resolve(),
        ]);
    }

    private function resolveHotelForEventSlug(string $eventSlug, string $hotelSlug): Hotel
    {
        return Hotel::query()
            ->active()
            ->where('slug', $hotelSlug)
            ->whereHas('events', fn ($q) => $q->where('events.slug', $eventSlug)
                ->where('events.is_active', true)
                ->where('hotel_event.is_active', true))
            ->firstOrFail();
    }

    private function ensureHotelAttachedToEvent(Event $event, Hotel $hotel): void
    {
        $exists = DB::table('hotel_event')
            ->where('event_id', $event->id)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->exists();

        abort_if(! $exists, 422, 'Hotel is not active for this event.');
    }
}
