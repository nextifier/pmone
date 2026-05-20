<?php

namespace App\Services\Reservation;

use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\HotelTransferOption;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\ReservationTransfer;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Builds an unsaved Reservation instance from a payload for preview/validate flows.
 *
 * Useful when we need a Purchasable entity to feed into PricingService::preview()
 * or PromoCodeService::validate() without actually persisting the reservation.
 *
 * Pricing mirrors ReservationService::previewSubtotal so preview total matches the
 * actual reservation total at create time (dynamic pricing periods, allotment
 * base_rate_override, allotment surcharge).
 *
 * No DB writes - all relations are set via setRelation() with in-memory collections.
 */
class TransientReservationBuilder
{
    public function __construct(
        protected ReservationService $reservations,
    ) {}

    /**
     * @param  array{
     *     hotel_id: int,
     *     event_id?: int|null,
     *     items?: array,
     *     transfers?: array,
     *     guest_email?: string,
     * }  $payload
     */
    public function build(array $payload): Reservation
    {
        $hotel = Hotel::query()->findOrFail($payload['hotel_id']);

        $reservation = new Reservation;
        $reservation->id = 0;
        $reservation->hotel_id = $hotel->id;
        $reservation->event_id = (int) ($payload['event_id'] ?? 0);
        $reservation->guest_email = $payload['guest_email'] ?? '';

        $subtotalRooms = 0.0;
        $surchargeAmount = 0.0;
        $items = collect();

        foreach ($payload['items'] ?? [] as $item) {
            $roomType = $hotel->roomTypes()->find($item['room_type_id'] ?? null);
            if (! $roomType) {
                continue;
            }

            $checkIn = $item['check_in_date'] ?? null;
            $checkOut = $item['check_out_date'] ?? null;
            $qty = (int) ($item['qty'] ?? 1);

            if (! $checkIn || ! $checkOut) {
                continue;
            }

            $checkInCarbon = Carbon::parse($checkIn);
            $checkOutCarbon = Carbon::parse($checkOut);

            if ($checkInCarbon->greaterThanOrEqualTo($checkOutCarbon)) {
                continue;
            }

            $allotment = HotelEventAllotment::query()
                ->active()
                ->where('hotel_id', $hotel->id)
                ->where('room_type_id', $roomType->id)
                ->whereDate('start_date', '<=', $checkInCarbon->toDateString())
                ->whereDate('end_date', '>=', $checkOutCarbon->toDateString())
                ->orderBy('id')
                ->first();

            try {
                $preview = $this->reservations->previewSubtotal($roomType, $checkInCarbon, $checkOutCarbon, $qty, $allotment);
            } catch (ValidationException $e) {
                continue;
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            $subtotalRooms += $preview['subtotal'];
            $surchargeAmount += $preview['surcharge'];

            $reservationItem = new ReservationItem;
            $reservationItem->room_type_id = $roomType->id;
            $reservationItem->check_in_date = $checkIn;
            $reservationItem->check_out_date = $checkOut;
            $reservationItem->nights = $preview['nights'];
            $reservationItem->qty = $qty;
            $reservationItem->rate_per_night = $preview['rate_per_night_avg'];
            $reservationItem->subtotal = $preview['subtotal'];

            $items->push($reservationItem);
        }

        $subtotalTransfer = 0.0;
        $transfers = collect();

        foreach ($payload['transfers'] ?? [] as $transfer) {
            $option = HotelTransferOption::query()
                ->where('hotel_id', $hotel->id)
                ->where('id', $transfer['transfer_option_id'] ?? null)
                ->where('is_active', true)
                ->first();

            if (! $option) {
                continue;
            }

            $subtotalTransfer += (float) $option->price;

            $reservationTransfer = new ReservationTransfer;
            $reservationTransfer->transfer_option_id = $option->id;
            $reservationTransfer->price = (float) $option->price;
            $transfers->push($reservationTransfer);
        }

        $reservation->subtotal_rooms = $subtotalRooms;
        $reservation->subtotal_transfer = $subtotalTransfer;
        $reservation->surcharge_amount = $surchargeAmount;
        $reservation->penalty_amount = 0;
        $reservation->discount_amount = 0;
        $reservation->tax_amount = 0;
        $reservation->service_charge_amount = 0;
        // Surcharge is already folded into $subtotalRooms - do not add it again.
        $reservation->total_amount = $subtotalRooms + $subtotalTransfer;

        $reservation->setRelation('hotel', $hotel);
        $reservation->setRelation('items', $items);
        $reservation->setRelation('transfers', $transfers);
        $reservation->setRelation('adjustments', collect());

        return $reservation;
    }
}
