<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exports\ReservationsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\CancelReservationRequest;
use App\Http\Requests\Reservation\ManualRefundRequest;
use App\Http\Requests\Reservation\StoreManualReservationRequest;
use App\Http\Requests\Reservation\UploadVoucherRequest;
use App\Http\Resources\ReservationIndexResource;
use App\Http\Resources\ReservationResource;
use App\Jobs\Reservation\ProcessXenditRefundJob;
use App\Jobs\Reservation\SendCancellationJob;
use App\Jobs\Reservation\SendHotelVoucherJob;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\Reservation;
use App\Services\Promotion\PenaltyService;
use App\Services\Promotion\PromoCodeService;
use App\Services\Reservation\DocumentService;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservations,
        protected DocumentService $documents,
        protected PromoCodeService $promoCodes,
        protected PenaltyService $penalties,
    ) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $query = Reservation::query()
            ->where('event_id', $event->id)
            ->with(['hotel', 'event', 'items.roomType', 'media', 'paymentGateway']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $items = $query->paginate((int) $request->input('per_page', 15));

        $availablePaymentChannels = Reservation::query()
            ->where('event_id', $event->id)
            ->whereNotNull('payment_channel')
            ->distinct()
            ->orderBy('payment_channel')
            ->pluck('payment_channel');

        return response()->json([
            'data' => ReservationIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'payment_channels' => $availablePaymentChannels,
            ],
        ]);
    }

    public function show(Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        $reservation->load([
            'hotel', 'event',
            'items.roomType',
            'transfers.transferOption',
            'paymentGateway',
            'adjustments.promotionRule', 'adjustments.promoCode',
            'media', 'creator', 'updater',
        ]);

        return response()->json(['data' => (new ReservationResource($reservation))->resolve()]);
    }

    public function activityLog(Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if (! auth()->user()?->can('reservations.read')) {
            abort(403);
        }

        $activities = $reservation->activities()
            ->with('causer:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'changes' => $a->properties['attributes'] ?? null,
                'previous' => $a->properties['old'] ?? null,
                'causer' => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $activities]);
    }

    public function destroy(Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if (! auth()->user()?->can('reservations.delete')) {
            abort(403);
        }

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully']);
    }

    public function bulkDestroy(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('reservations.delete')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:reservations,id'],
        ]);

        $deletedCount = 0;

        Reservation::query()
            ->whereIn('id', $validated['ids'])
            ->where('event_id', $event->id)
            ->get()
            ->each(function (Reservation $reservation) use (&$deletedCount) {
                $reservation->delete();
                $deletedCount++;
            });

        if ($deletedCount > 0) {
            activity()
                ->causedBy($request->user())
                ->event('bulk_deleted')
                ->withProperties([
                    'project_id' => $event->project_id,
                    'event_id' => $event->id,
                    'deleted_count' => $deletedCount,
                    'model_type' => 'Reservation',
                ])
                ->log("Bulk deleted {$deletedCount} reservation(s)");
        }

        return response()->json([
            'message' => "{$deletedCount} reservation(s) deleted",
            'deleted_count' => $deletedCount,
            'errors' => [],
        ]);
    }

    public function trash(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('reservations.delete')) {
            abort(403);
        }

        $query = Reservation::onlyTrashed()
            ->where('event_id', $event->id)
            ->with(['hotel', 'event', 'items.roomType', 'media', 'paymentGateway']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $items = $query->paginate((int) $request->input('per_page', 15));

        $availablePaymentChannels = Reservation::onlyTrashed()
            ->where('event_id', $event->id)
            ->whereNotNull('payment_channel')
            ->distinct()
            ->orderBy('payment_channel')
            ->pluck('payment_channel');

        return response()->json([
            'data' => ReservationIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'payment_channels' => $availablePaymentChannels,
            ],
        ]);
    }

    public function restore(Event $event, int $id): JsonResponse
    {
        if (! auth()->user()?->can('reservations.delete')) {
            abort(403);
        }

        $reservation = Reservation::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $reservation->restore();

        return response()->json(['message' => 'Reservation restored successfully']);
    }

    public function bulkRestore(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('reservations.delete')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:reservations,id'],
        ]);

        $restoredCount = 0;

        Reservation::onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->where('event_id', $event->id)
            ->get()
            ->each(function (Reservation $reservation) use (&$restoredCount) {
                $reservation->restore();
                $restoredCount++;
            });

        if ($restoredCount > 0) {
            activity()
                ->causedBy($request->user())
                ->event('bulk_restored')
                ->withProperties([
                    'project_id' => $event->project_id,
                    'event_id' => $event->id,
                    'restored_count' => $restoredCount,
                    'model_type' => 'Reservation',
                ])
                ->log("Bulk restored {$restoredCount} reservation(s)");
        }

        return response()->json([
            'message' => "{$restoredCount} reservation(s) restored",
            'restored_count' => $restoredCount,
            'errors' => [],
        ]);
    }

    public function forceDestroy(Request $request, Event $event, int $id): JsonResponse
    {
        if (! auth()->user()?->can('reservations.delete')) {
            abort(403);
        }

        $reservation = Reservation::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        activity()
            ->causedBy($request->user())
            ->event('force_deleted')
            ->withProperties([
                'project_id' => $event->project_id,
                'event_id' => $event->id,
                'reservation_id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
            ])
            ->log('Reservation permanently deleted');

        $reservation->forceDelete();

        return response()->json(['message' => 'Reservation permanently deleted']);
    }

    public function bulkForceDestroy(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('reservations.delete')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:reservations,id'],
        ]);

        $deletedCount = 0;

        Reservation::onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->where('event_id', $event->id)
            ->get()
            ->each(function (Reservation $reservation) use (&$deletedCount) {
                $reservation->forceDelete();
                $deletedCount++;
            });

        if ($deletedCount > 0) {
            activity()
                ->causedBy($request->user())
                ->event('bulk_force_deleted')
                ->withProperties([
                    'project_id' => $event->project_id,
                    'event_id' => $event->id,
                    'deleted_count' => $deletedCount,
                    'model_type' => 'Reservation',
                ])
                ->log("Bulk permanently deleted {$deletedCount} reservation(s)");
        }

        return response()->json([
            'message' => "{$deletedCount} reservation(s) permanently deleted",
            'deleted_count' => $deletedCount,
            'errors' => [],
        ]);
    }

    public function storeManual(StoreManualReservationRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();
        $data['source'] = ReservationSource::AdminManual;

        // Ensure hotel is attached to the route event via pivot
        $hotel = Hotel::query()->findOrFail($data['hotel_id']);
        $pivot = HotelEvent::query()
            ->where(['hotel_id' => $hotel->id, 'event_id' => $event->id, 'is_active' => true])
            ->first();
        abort_if(! $pivot, 404, 'Hotel is not active for this event.');

        $data['event_id'] = $event->id;

        $mode = $data['payment_mode'] ?? 'xendit';
        unset($data['payment_mode']);

        if ($mode === 'skip') {
            $data['skip_payment'] = true;
        } elseif ($mode === 'manual_paid') {
            $data['mark_paid_manual'] = true;
        } else {
            $data['generate_xendit'] = true;
        }

        $reservation = $this->reservations->createReservation($data);

        return response()->json([
            'data' => (new ReservationResource($reservation))->resolve(),
            'message' => 'Reservation created successfully',
        ], 201);
    }

    public function uploadVoucher(UploadVoucherRequest $request, Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        $tmpFolder = $request->input('tmp_voucher');

        if (is_string($tmpFolder) && Str::startsWith($tmpFolder, 'tmp-')) {
            $this->attachTempVoucher($reservation, $tmpFolder);
        } else {
            $reservation->clearMediaCollection('voucher');
            $reservation->addMediaFromRequest('voucher')->toMediaCollection('voucher');
        }

        return response()->json([
            'message' => 'Voucher uploaded successfully',
            'voucher' => [
                'name' => $reservation->getFirstMedia('voucher')?->name,
                'url' => $reservation->getFirstMediaUrl('voucher'),
            ],
        ]);
    }

    private function attachTempVoucher(Reservation $reservation, string $tmpFolder): void
    {
        $metadataPath = "tmp/uploads/{$tmpFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            abort(422, 'Temporary voucher file not found.');
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpFolder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            abort(422, 'Temporary voucher file is missing.');
        }

        $reservation->clearMediaCollection('voucher');
        $reservation->addMedia(Storage::disk('local')->path($filePath))->toMediaCollection('voucher');

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");
    }

    public function deleteVoucher(Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if (! auth()->user()?->can('reservations.upload_voucher')) {
            abort(403);
        }

        $reservation->clearMediaCollection('voucher');

        return response()->json(['message' => 'Voucher deleted']);
    }

    public function sendVoucher(Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if (! auth()->user()?->can('reservations.send_voucher')) {
            abort(403);
        }

        if (! $reservation->hasMedia('voucher')) {
            return response()->json(['message' => 'Voucher must be uploaded first'], 422);
        }

        if (! $reservation->status->isPaid()) {
            abort(422, 'Voucher can only be sent for a paid reservation.');
        }

        // Throttle to one send per minute per reservation to prevent the
        // admin spamming the "Resend Voucher" button.
        $rateLimitKey = 'send-voucher:'.$reservation->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => "Please wait {$seconds} seconds before resending the voucher.",
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 60);

        SendHotelVoucherJob::dispatch($reservation->id);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($reservation)
            ->event('voucher_sent')
            ->withProperties([
                'project_id' => $event->project_id,
                'reservation_id' => $reservation->id,
            ])
            ->log('Hotel voucher sent to guest');

        return response()->json(['message' => 'Voucher email sent']);
    }

    public function cancel(CancelReservationRequest $request, Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        // C4: Block cancellation of already-final reservations to preserve audit trail.
        if ($reservation->status->isFinal()) {
            abort(422, "Cannot cancel a {$reservation->status->label()} reservation.");
        }

        $data = $request->validated();

        // Void all active adjustments + revert promo usage counters BEFORE status flip.
        // Preserves total_amount so refund calculation is correct.
        $this->promoCodes->voidAllOnCancel($reservation);

        // Lock in the refund from what the guest actually paid - BEFORE applying
        // any cancellation fee. applyCancellationFee() records a penalty
        // adjustment that recalculates total_amount upward; computing the refund
        // first keeps it tied to the amount paid, never inflated by the fee.
        $refundAmount = $data['refund_amount'] ?? $this->reservations->calculateRefund($reservation->fresh());

        // Apply any cancellation_window penalty rule that matches - recorded as
        // an AppliedAdjustment for audit only; it does not affect the refund.
        $this->penalties->applyCancellationFee($reservation->fresh(['items', 'transfers', 'adjustments', 'hotel']));

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'],
            'refund_amount' => $refundAmount,
            'refund_reason' => $data['reason'],
        ]);

        if (($data['process_refund'] ?? true) && $refundAmount > 0 && $reservation->xendit_invoice_id) {
            ProcessXenditRefundJob::dispatch($reservation->id, (float) $refundAmount, $data['reason']);
        }

        SendCancellationJob::dispatch($reservation->id, (float) $refundAmount);

        activity()
            ->causedBy($request->user())
            ->performedOn($reservation)
            ->event('reservation_cancelled')
            ->withProperties([
                'project_id' => $event->project_id,
                'reservation_id' => $reservation->id,
                'reason' => $data['reason'],
                'refund_amount' => (float) $refundAmount,
                'process_refund' => (bool) ($data['process_refund'] ?? true),
            ])
            ->log('Reservation cancelled');

        return response()->json([
            'message' => 'Reservation cancelled',
            'refund_amount' => (float) $refundAmount,
        ]);
    }

    /**
     * Manually flip a pending_payment reservation to paid. Backs the staff
     * action that lives behind `reservations.mark_paid` permission. Used when
     * payment lands outside Xendit (cash, manual bank transfer, voucher) or
     * when the Xendit webhook never reached the server (localhost dev,
     * misconfigured allowlist). Routes through the same {@see ReservationService::markAsPaid}
     * call path so downstream effects — booking email, payment_channel
     * backfill, status conditional update — stay identical to the webhook
     * happy path.
     */
    public function markPaid(Request $request, Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if ($reservation->status !== ReservationStatus::PendingPayment) {
            abort(422, "Only pending payment reservations can be marked as paid. Current status: {$reservation->status->label()}.");
        }

        $data = $request->validate([
            'payment_channel' => ['nullable', 'string', 'max:50'],
            'payment_destination' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [];
        if (! empty($data['payment_channel'])) {
            $payload['payment_channel'] = strtoupper($data['payment_channel']);
        }
        if (! empty($data['payment_destination'])) {
            $payload['payment_destination'] = $data['payment_destination'];
        }

        $this->reservations->markAsPaid($reservation, $payload);

        activity()
            ->causedBy($request->user())
            ->performedOn($reservation)
            ->event('reservation_marked_paid_manual')
            ->withProperties([
                'project_id' => $event->project_id,
                'reservation_id' => $reservation->id,
                'payment_channel' => $payload['payment_channel'] ?? null,
                'note' => $data['note'] ?? null,
            ])
            ->log('Reservation manually marked as paid by staff');

        return response()->json([
            'message' => 'Reservation marked as paid.',
            'data' => new ReservationResource($reservation->fresh()),
        ]);
    }

    /**
     * Mark an outstanding refund as completed manually. Used for payment
     * channels (Virtual Account, retail outlets) that Xendit does not refund
     * via API — admin must transfer money back to the guest manually and then
     * record completion here. Sets `refunded_at` + flips status to
     * {@see ReservationStatus::Refunded}. The bank reference (if provided) +
     * the admin's note land in the activity log for audit.
     */
    public function manualRefund(ManualRefundRequest $request, Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if ($reservation->status !== ReservationStatus::Cancelled) {
            abort(422, "Manual refund only applies to cancelled reservations. Current status: {$reservation->status->label()}.");
        }

        if ($reservation->refunded_at !== null) {
            abort(422, 'This reservation has already been refunded on '.$reservation->refunded_at->toDateTimeString().'.');
        }

        if ($reservation->refund_amount === null || (float) $reservation->refund_amount <= 0) {
            abort(422, 'No refund amount is recorded for this reservation.');
        }

        if ($reservation->xendit_refund_id !== null) {
            abort(422, 'A Xendit refund is already in progress for this reservation.');
        }

        $data = $request->validated();

        $reservation->update([
            'status' => ReservationStatus::Refunded,
            'refunded_at' => now(),
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($reservation)
            ->event('refund_completed_manual')
            ->withProperties([
                'project_id' => $event->project_id,
                'reservation_id' => $reservation->id,
                'refund_amount' => (float) $reservation->refund_amount,
                'note' => $data['note'],
                'bank_reference' => $data['bank_reference'] ?? null,
            ])
            ->log('Manual refund completed by staff');

        return response()->json([
            'message' => 'Manual refund recorded.',
            'data' => new ReservationResource($reservation->fresh()),
        ]);
    }

    public function export(Request $request, Event $event): BinaryFileResponse
    {
        if (! auth()->user()?->can('reservations.export')) {
            abort(403);
        }

        $filters = array_filter([
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'payment_channel' => $request->input('filter_payment_channel'),
            'mode' => $request->input('filter_mode'),
            'event_id' => $event->id,
            'hotel_id' => $request->input('filter_hotel_id'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
        ]);

        $sort = $request->input('sort', '-created_at');

        $filename = 'reservations_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'project_id' => $event->project_id,
                'model_type' => 'Reservation',
                'event_id' => $event->id,
                'filename' => $filename,
            ])
            ->log('Exported reservations');

        return Excel::download(new ReservationsExport($filters ?: null, $sort), $filename);
    }

    public function invoicePdf(Event $event, Reservation $reservation): Response
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if (! auth()->user()?->can('reservations.view_documents')) {
            abort(403);
        }

        return $this->documents->renderInvoicePdf($reservation);
    }

    public function receiptPdf(Event $event, Reservation $reservation): Response
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        if (! auth()->user()?->can('reservations.view_documents')) {
            abort(403);
        }

        if (! $reservation->status->isPaid()) {
            abort(422, 'Receipt is only available after payment.');
        }

        return $this->documents->renderReceiptPdf($reservation);
    }

    private function ensureReservationBelongsToEvent(Event $event, Reservation $reservation): void
    {
        abort_if($reservation->event_id !== $event->id, 404);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->input('filter_search')) {
            $escaped = addcslashes((string) $search, '%_\\');
            $like = '%'.strtolower($escaped).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(reservation_number) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(guest_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(guest_email) LIKE ?', [$like]);
            });
        }

        if ($status = $request->input('filter_status')) {
            $statuses = is_array($status) ? $status : explode(',', $status);
            $query->whereIn('status', array_filter($statuses));
        }

        if ($paymentChannel = $request->input('filter_payment_channel')) {
            $channels = array_filter(is_array($paymentChannel) ? $paymentChannel : explode(',', $paymentChannel));
            if ($channels) {
                $query->whereIn('payment_channel', $channels);
            }
        }

        if ($mode = $request->input('filter_mode')) {
            $modes = array_filter(is_array($mode) ? $mode : explode(',', $mode));
            if ($modes) {
                $query->whereHas('paymentGateway', fn ($q) => $q->whereIn('mode', $modes));
            }
        }

        if ($hotelId = $request->input('filter_hotel_id')) {
            $query->where('hotel_id', $hotelId);
        }

        if ($from = $request->input('filter_date_from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->input('filter_date_to')) {
            $query->where('created_at', '<=', $to);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $fieldMap = [
            'reservation_number' => 'reservation_number',
            'guest_name' => 'guest_name',
            'total_amount' => 'total_amount',
            'created_at' => 'created_at',
            'paid_at' => 'paid_at',
            'status' => 'status',
            'deleted_at' => 'deleted_at',
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
