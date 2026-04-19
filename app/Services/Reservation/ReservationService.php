<?php

namespace App\Services\Reservation;

use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Services\Xendit\XenditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        protected XenditService $xendit,
    ) {}

    /**
     * Check available quantity for a date range from allotments.
     * Returns remaining capacity (allotment quantity - already-committed reservations).
     */
    public function checkAvailability(?int $eventId, int $hotelId, int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $allotmentQuery = HotelEventAllotment::query()
            ->active()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->where('start_date', '<=', $checkIn)
            ->where('end_date', '>=', $checkOut);

        $hasAllotment = $allotmentQuery->exists();
        $totalQuantity = (int) $allotmentQuery->sum('quantity');

        // No allotment row at all -> treat as unlimited (hotel without allotment acts as open booking).
        if (! $hasAllotment) {
            return PHP_INT_MAX;
        }

        $committed = (int) ReservationItem::query()
            ->whereHas('reservation', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId)
                    ->whereIn('status', [
                        ReservationStatus::PendingPayment->value,
                        ReservationStatus::Paid->value,
                        ReservationStatus::VoucherSent->value,
                    ]);
            })
            ->where('room_type_id', $roomTypeId)
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn);
            })
            ->sum('qty');

        return max(0, $totalQuantity - $committed);
    }

    /**
     * Create reservation atomically: validate availability, lock, create, generate Xendit invoice.
     */
    public function createReservation(array $data, ?XenditService $xendit = null): Reservation
    {
        return DB::transaction(function () use ($data, $xendit) {
            $hotel = Hotel::query()->findOrFail($data['hotel_id']);

            // Always derive event_id from hotel (hotels are event-scoped).
            $data['event_id'] = $hotel->event_id;

            $items = $data['items'] ?? [];
            $transfers = $data['transfers'] ?? [];

            if (empty($items)) {
                abort(422, 'At least one room item is required.');
            }

            $subtotalRooms = 0;
            $subtotalTransfer = 0;
            $surchargeAmount = 0;
            $itemPayloads = [];

            foreach ($items as $item) {
                $roomType = RoomType::query()
                    ->where('id', $item['room_type_id'])
                    ->where('hotel_id', $hotel->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $checkIn = Carbon::parse($item['check_in_date']);
                $checkOut = Carbon::parse($item['check_out_date']);
                $nights = $checkIn->diffInDays($checkOut);

                if ($nights < 1) {
                    abort(422, 'Check-out must be after check-in.');
                }

                $qty = (int) ($item['qty'] ?? 1);

                // Acquire lock on overlapping allotments BEFORE availability check to prevent race condition.
                HotelEventAllotment::query()
                    ->active()
                    ->where('hotel_id', $hotel->id)
                    ->where('room_type_id', $roomType->id)
                    ->where('start_date', '<=', $checkIn->toDateString())
                    ->where('end_date', '>=', $checkOut->toDateString())
                    ->lockForUpdate()
                    ->get();

                $available = $this->checkAvailability(
                    $data['event_id'] ?? null,
                    $hotel->id,
                    $roomType->id,
                    $checkIn->toDateString(),
                    $checkOut->toDateString(),
                );

                if ($available < $qty) {
                    abort(422, "Not enough rooms available for {$roomType->name}. Only {$available} left.");
                }

                $allotment = HotelEventAllotment::query()
                    ->active()
                    ->where('hotel_id', $hotel->id)
                    ->where('room_type_id', $roomType->id)
                    ->where('start_date', '<=', $checkIn->toDateString())
                    ->where('end_date', '>=', $checkOut->toDateString())
                    ->first();

                $rate = (float) $roomType->base_rate;

                if ($allotment && $allotment->surcharge_type === 'fixed') {
                    $rate += (float) $allotment->surcharge_amount;
                    $surchargeAmount += (float) $allotment->surcharge_amount * $nights * $qty;
                } elseif ($allotment && $allotment->surcharge_type === 'percentage') {
                    $surchargeAdd = $rate * ((float) $allotment->surcharge_amount / 100);
                    $rate += $surchargeAdd;
                    $surchargeAmount += $surchargeAdd * $nights * $qty;
                }

                $subtotal = $rate * $nights * $qty;
                $subtotalRooms += $subtotal;

                $itemPayloads[] = [
                    'room_type_id' => $roomType->id,
                    'allotment_id' => $allotment?->id,
                    'check_in_date' => $checkIn->toDateString(),
                    'check_out_date' => $checkOut->toDateString(),
                    'nights' => $nights,
                    'qty' => $qty,
                    'guest_name' => $item['guest_name'] ?? null,
                    'guest_identity' => $item['guest_identity'] ?? null,
                    'rate_per_night' => $rate,
                    'subtotal' => $subtotal,
                ];
            }

            foreach ($transfers as $transfer) {
                $subtotalTransfer += (float) ($transfer['price'] ?? 0);
            }

            $taxRate = (float) $hotel->tax_percentage / 100;
            $serviceRate = (float) $hotel->service_charge_percentage / 100;
            $taxableBase = $subtotalRooms + $subtotalTransfer;
            $taxAmount = round($taxableBase * $taxRate, 2);
            $serviceAmount = round($taxableBase * $serviceRate, 2);
            $total = $taxableBase + $taxAmount + $serviceAmount;

            [$rawToken, $hashedToken] = $this->generateMagicLinkToken();

            $reservation = Reservation::create([
                'reservation_number' => $this->generateReservationNumber(),
                'event_id' => $data['event_id'],
                'hotel_id' => $hotel->id,
                'status' => $data['status'] ?? ReservationStatus::PendingPayment,
                'payment_expires_at' => now()->addSeconds(config('xendit.invoice_duration', 86400)),
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'],
                'guest_identity_type' => $data['guest_identity_type'],
                'guest_identity_number' => $data['guest_identity_number'],
                'guest_nationality' => $data['guest_nationality'] ?? null,
                'guest_company' => $data['guest_company'] ?? null,
                'special_request' => $data['special_request'] ?? null,
                'subtotal_rooms' => $subtotalRooms,
                'subtotal_transfer' => $subtotalTransfer,
                'surcharge_amount' => $surchargeAmount,
                'tax_amount' => $taxAmount,
                'service_charge_amount' => $serviceAmount,
                'discount_amount' => 0,
                'total_amount' => $total,
                'magic_link_token' => $hashedToken,
                'source' => $data['source'] ?? ReservationSource::PublicWebsite,
                'project_username' => $data['project_username'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemPayloads as $payload) {
                $reservation->items()->create($payload);
            }

            foreach ($transfers as $transfer) {
                $reservation->transfers()->create([
                    'transfer_option_id' => $transfer['transfer_option_id'],
                    'direction' => $transfer['direction'],
                    'transfer_date' => $transfer['transfer_date'],
                    'transfer_time' => $transfer['transfer_time'] ?? null,
                    'pickup_location' => $transfer['pickup_location'] ?? null,
                    'dropoff_location' => $transfer['dropoff_location'] ?? null,
                    'flight_number' => $transfer['flight_number'] ?? null,
                    'flight_time' => $transfer['flight_time'] ?? null,
                    'pax_count' => $transfer['pax_count'] ?? 1,
                    'luggage_count' => $transfer['luggage_count'] ?? null,
                    'note' => $transfer['note'] ?? null,
                    'price' => $transfer['price'] ?? 0,
                ]);
            }

            $reservation->magicLinkRaw = $rawToken;

            if (($data['skip_payment'] ?? false) === true) {
                $reservation->update([
                    'status' => ReservationStatus::Paid,
                    'paid_at' => now(),
                    'payment_method' => PaymentMethod::Complimentary,
                ]);
                SendBookingReceivedJob::dispatch($reservation->id, $rawToken);
            } elseif (($data['mark_paid_manual'] ?? false) === true) {
                $reservation->update([
                    'status' => ReservationStatus::Paid,
                    'paid_at' => now(),
                    'payment_method' => PaymentMethod::ManualBankTransfer,
                ]);
                SendBookingReceivedJob::dispatch($reservation->id, $rawToken);
            } elseif (($data['generate_xendit'] ?? true) === true) {
                try {
                    $xenditClient = $xendit ?? $this->xendit;
                    $frontendUrl = rtrim(config('app.frontend_url'), '/');
                    $successUrl = "{$frontendUrl}/hotels/reservation/{$rawToken}";
                    $failureUrl = "{$frontendUrl}/accommodation?failed=".$reservation->reservation_number;
                    $invoice = $xenditClient->createInvoice($reservation, $successUrl, $failureUrl);
                    $reservation->update([
                        'xendit_invoice_id' => $invoice['invoice_id'],
                        'payment_url' => $invoice['invoice_url'],
                        'payment_method' => PaymentMethod::Xendit,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Xendit invoice creation failed', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $fresh = $reservation->fresh(['items', 'transfers', 'hotel', 'event']);
            $fresh->magicLinkRaw = $rawToken;

            return $fresh;
        });
    }

    public function markAsPaid(Reservation $reservation, ?string $xenditInvoiceId = null): void
    {
        if ($reservation->status->isPaid()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Paid,
            'paid_at' => now(),
            'xendit_invoice_id' => $xenditInvoiceId ?? $reservation->xendit_invoice_id,
            'payment_method' => $reservation->payment_method ?? PaymentMethod::Xendit,
        ]);

        SendBookingReceivedJob::dispatch($reservation->id);
    }

    public function expireReservation(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::PendingPayment) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Expired,
        ]);
    }

    public function calculateRefund(Reservation $reservation): float
    {
        $earliestCheckIn = $reservation->items()
            ->orderBy('check_in_date')
            ->value('check_in_date');

        if (! $earliestCheckIn) {
            return 0.0;
        }

        $daysUntil = now()->startOfDay()->diffInDays(Carbon::parse($earliestCheckIn)->startOfDay(), false);
        $total = (float) $reservation->total_amount;

        if ($daysUntil >= 14) {
            return round($total, 2);
        }

        if ($daysUntil >= 7) {
            return round($total * 0.5, 2);
        }

        return 0.0;
    }

    public function generateReservationNumber(): string
    {
        $prefix = 'HTL-'.now()->format('Ymd').'-';

        do {
            $candidate = $prefix.strtoupper(Str::random(4));
        } while (Reservation::query()->where('reservation_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return array{0: string, 1: string} [raw, hashed]
     */
    public function generateMagicLinkToken(): array
    {
        $raw = Str::random(48);
        $hashed = hash('sha256', $raw);

        return [$raw, $hashed];
    }
}
