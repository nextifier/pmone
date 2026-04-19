<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exports\ReservationsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\CancelReservationRequest;
use App\Http\Requests\Reservation\StoreManualReservationRequest;
use App\Http\Requests\Reservation\UploadVoucherRequest;
use App\Http\Resources\ReservationIndexResource;
use App\Http\Resources\ReservationResource;
use App\Jobs\Reservation\ProcessXenditRefundJob;
use App\Jobs\Reservation\SendCancellationJob;
use App\Jobs\Reservation\SendHotelVoucherJob;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Services\Reservation\DocumentService;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $query = Reservation::query()
            ->where('event_id', $event->id)
            ->with(['hotel', 'event', 'items', 'media']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $items = $query->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => ReservationIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
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
            'media', 'creator', 'updater',
        ]);

        return response()->json(['data' => (new ReservationResource($reservation))->resolve()]);
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

    public function storeManual(StoreManualReservationRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();
        $data['source'] = ReservationSource::AdminManual;

        // Ensure hotel belongs to the route event
        $hotel = Hotel::where('id', $data['hotel_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();
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

        SendHotelVoucherJob::dispatch($reservation->id);

        return response()->json(['message' => 'Voucher email queued']);
    }

    public function cancel(CancelReservationRequest $request, Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureReservationBelongsToEvent($event, $reservation);

        $data = $request->validated();
        $refundAmount = $data['refund_amount'] ?? $this->reservations->calculateRefund($reservation);

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

        return response()->json([
            'message' => 'Reservation cancelled',
            'refund_amount' => (float) $refundAmount,
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
            'event_id' => $event->id,
            'hotel_id' => $request->input('filter_hotel_id'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
        ]);

        $sort = $request->input('sort', '-created_at');

        $filename = 'reservations_'.now()->format('Y-m-d_His').'.xlsx';

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
            $query->where(function ($q) use ($search) {
                $q->where('reservation_number', 'ilike', "%{$search}%")
                    ->orWhere('guest_name', 'ilike', "%{$search}%")
                    ->orWhere('guest_email', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('filter_status')) {
            $statuses = is_array($status) ? $status : explode(',', $status);
            $query->whereIn('status', array_filter($statuses));
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
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
