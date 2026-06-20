<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendeesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendee\UpdateEventAttendeeRequest;
use App\Http\Resources\AttendeeIndexResource;
use App\Http\Resources\AttendeeResource;
use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Services\Ticket\AttendeeService;
use App\Services\Ticket\TicketDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class EventAttendeeController extends Controller
{
    private const WITH = [
        'ticket',
        'checkedInBy',
        'ticketOrderItem.selectedEventDay',
        'ticketOrderItem.ticketSession',
        'ticketOrderItem.ticketOrder.paymentGateway',
    ];

    public function index(Request $request, Event $event): JsonResponse
    {
        $query = Attendee::query()
            ->forEvent($event->id)
            ->with(self::WITH);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $items = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'data' => AttendeeIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'payment_channels' => $this->availablePaymentChannels($event),
            ],
        ]);
    }

    public function update(UpdateEventAttendeeRequest $request, Event $event, Attendee $attendee, AttendeeService $service): JsonResponse
    {
        $this->ensureAttendeeBelongsToEvent($event, $attendee);

        $updated = $service->applyStaffEdit($attendee, $request->validated(), $event, (int) $request->user()->id);

        return response()->json([
            'message' => 'Attendee updated.',
            'data' => new AttendeeResource($updated->load(self::WITH)),
        ]);
    }

    public function destroy(Event $event, Attendee $attendee): JsonResponse
    {
        $this->ensureAttendeeBelongsToEvent($event, $attendee);

        if (! auth()->user()?->can('attendees.delete')) {
            abort(403);
        }

        $attendee->delete();

        return response()->json(['message' => 'Attendee deleted successfully']);
    }

    public function bulkDestroy(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('attendees.delete')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:attendees,id'],
        ]);

        $deletedCount = 0;

        Attendee::query()
            ->forEvent($event->id)
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(function (Attendee $attendee) use (&$deletedCount) {
                $attendee->delete();
                $deletedCount++;
            });

        $this->logBulk($request, $event, 'bulk_deleted', $deletedCount, "Bulk deleted {$deletedCount} attendee(s)");

        return response()->json([
            'message' => "{$deletedCount} attendee(s) deleted",
            'deleted_count' => $deletedCount,
            'errors' => [],
        ]);
    }

    public function bulkCheckIn(Request $request, Event $event, AttendeeService $service): JsonResponse
    {
        if (! auth()->user()?->can('attendees.update')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:attendees,id'],
            'checked_in' => ['required', 'boolean'],
        ]);

        $checkedIn = (bool) $validated['checked_in'];
        $staffId = (int) $request->user()->id;
        $updatedCount = 0;

        Attendee::query()
            ->forEvent($event->id)
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(function (Attendee $attendee) use ($service, $checkedIn, $staffId, $event, &$updatedCount) {
                $changed = ($attendee->checked_in_at !== null) !== $checkedIn;
                $service->setCheckIn($attendee, $checkedIn, $staffId, $event->id);
                if ($changed) {
                    $updatedCount++;
                }
            });

        $verb = $checkedIn ? 'checked in' : 'marked not checked in';
        $this->logBulk($request, $event, $checkedIn ? 'bulk_checked_in' : 'bulk_unchecked', $updatedCount, "Bulk {$verb} {$updatedCount} attendee(s)");

        return response()->json([
            'message' => "{$updatedCount} attendee(s) {$verb}",
            'updated_count' => $updatedCount,
            'errors' => [],
        ]);
    }

    public function resendETicket(Request $request, Event $event, Attendee $attendee): JsonResponse
    {
        $this->ensureAttendeeBelongsToEvent($event, $attendee);

        if (! auth()->user()?->can('attendees.update')) {
            abort(403);
        }

        abort_if(blank($attendee->email), 422, 'Attendee has no email address.');

        SendAttendeeETicketJob::dispatch($attendee->id);

        activity()
            ->causedBy($request->user())
            ->event('resent_eticket')
            ->withProperties([
                'project_id' => $event->project_id,
                'event_id' => $event->id,
                'attendee_id' => $attendee->id,
                'model_type' => 'Attendee',
            ])
            ->log('Resent e-ticket email');

        return response()->json(['message' => 'E-ticket email is being sent.']);
    }

    public function bulkResendETicket(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('attendees.update')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:attendees,id'],
        ]);

        $sentCount = 0;

        Attendee::query()
            ->forEvent($event->id)
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->each(function (Attendee $attendee) use (&$sentCount) {
                SendAttendeeETicketJob::dispatch($attendee->id);
                $sentCount++;
            });

        $this->logBulk($request, $event, 'bulk_resent_eticket', $sentCount, "Bulk resent e-ticket to {$sentCount} attendee(s)");

        return response()->json([
            'message' => "E-ticket email is being sent to {$sentCount} attendee(s).",
            'sent_count' => $sentCount,
            'errors' => [],
        ]);
    }

    public function trash(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('attendees.delete')) {
            abort(403);
        }

        $query = Attendee::onlyTrashed()
            ->forEvent($event->id)
            ->with(self::WITH);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $items = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'data' => AttendeeIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'payment_channels' => $this->availablePaymentChannels($event),
            ],
        ]);
    }

    public function restore(Event $event, int $id): JsonResponse
    {
        if (! auth()->user()?->can('attendees.delete')) {
            abort(403);
        }

        Attendee::onlyTrashed()->forEvent($event->id)->findOrFail($id)->restore();

        return response()->json(['message' => 'Attendee restored successfully']);
    }

    public function bulkRestore(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('attendees.delete')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restoredCount = 0;

        Attendee::onlyTrashed()
            ->forEvent($event->id)
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(function (Attendee $attendee) use (&$restoredCount) {
                $attendee->restore();
                $restoredCount++;
            });

        $this->logBulk($request, $event, 'bulk_restored', $restoredCount, "Bulk restored {$restoredCount} attendee(s)");

        return response()->json([
            'message' => "{$restoredCount} attendee(s) restored",
            'restored_count' => $restoredCount,
            'errors' => [],
        ]);
    }

    public function forceDestroy(Request $request, Event $event, int $id): JsonResponse
    {
        if (! auth()->user()?->can('attendees.delete')) {
            abort(403);
        }

        $attendee = Attendee::onlyTrashed()->forEvent($event->id)->findOrFail($id);

        $this->logBulk($request, $event, 'force_deleted', 1, 'Attendee permanently deleted');

        $attendee->forceDelete();

        return response()->json(['message' => 'Attendee permanently deleted']);
    }

    public function bulkForceDestroy(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('attendees.delete')) {
            abort(403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deletedCount = 0;

        Attendee::onlyTrashed()
            ->forEvent($event->id)
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(function (Attendee $attendee) use (&$deletedCount) {
                $attendee->forceDelete();
                $deletedCount++;
            });

        $this->logBulk($request, $event, 'bulk_force_deleted', $deletedCount, "Bulk permanently deleted {$deletedCount} attendee(s)");

        return response()->json([
            'message' => "{$deletedCount} attendee(s) permanently deleted",
            'deleted_count' => $deletedCount,
            'errors' => [],
        ]);
    }

    public function export(Request $request, Event $event): BinaryFileResponse
    {
        if (! auth()->user()?->can('attendees.export')) {
            abort(403);
        }

        $filters = array_filter([
            'search' => $request->input('filter_search'),
            'checked_in' => $request->input('filter_checked_in'),
            'payment_channel' => $request->input('filter_payment_channel'),
            'mode' => $request->input('filter_mode'),
            'order_status' => $request->input('filter_order_status'),
            'event_id' => $event->id,
        ], fn ($v) => $v !== null && $v !== '');

        $sort = $request->input('sort', '-id');
        $filename = 'attendees_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'project_id' => $event->project_id,
                'model_type' => 'Attendee',
                'event_id' => $event->id,
                'filename' => $filename,
            ])
            ->log('Exported attendees');

        return Excel::download(new AttendeesExport($filters ?: null, $sort), $filename);
    }

    public function invoicePdf(Event $event, TicketOrder $order, TicketDocumentService $documents): Response
    {
        $this->ensureOrderBelongsToEvent($event, $order);

        if (! auth()->user()?->can('attendees.view_documents')) {
            abort(403);
        }

        return $documents->renderInvoicePdf($order);
    }

    public function receiptPdf(Event $event, TicketOrder $order, TicketDocumentService $documents): Response
    {
        $this->ensureOrderBelongsToEvent($event, $order);

        if (! auth()->user()?->can('attendees.view_documents')) {
            abort(403);
        }

        abort_if($order->paid_at === null, 422, 'Receipt is only available after payment.');

        return $documents->renderReceiptPdf($order);
    }

    private function availablePaymentChannels(Event $event)
    {
        return TicketOrder::query()
            ->where('event_id', $event->id)
            ->whereNotNull('payment_channel')
            ->distinct()
            ->orderBy('payment_channel')
            ->pluck('payment_channel');
    }

    private function ensureAttendeeBelongsToEvent(Event $event, Attendee $attendee): void
    {
        abort_unless($attendee->ticketOrderItem?->ticketOrder?->event_id === $event->id, 404);
    }

    private function ensureOrderBelongsToEvent(Event $event, TicketOrder $order): void
    {
        abort_if($order->event_id !== $event->id, 404);
    }

    private function logBulk(Request $request, Event $event, string $action, int $count, string $message): void
    {
        if ($count <= 0) {
            return;
        }

        activity()
            ->causedBy($request->user())
            ->event($action)
            ->withProperties([
                'project_id' => $event->project_id,
                'event_id' => $event->id,
                'count' => $count,
                'model_type' => 'Attendee',
            ])
            ->log($message);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = trim((string) $request->input('filter_search', $request->input('search')))) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%")
                    ->orWhere('qr_token', $search)
                    ->orWhereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->whereLike('order_number', "%{$search}%"));
            });
        }

        if ($request->filled('filter_checked_in')) {
            $values = is_array($request->input('filter_checked_in'))
                ? $request->input('filter_checked_in')
                : explode(',', (string) $request->input('filter_checked_in'));
            $wantsIn = in_array('in', $values, true);
            $wantsOut = in_array('out', $values, true);
            if ($wantsIn && ! $wantsOut) {
                $query->whereNotNull('checked_in_at');
            } elseif ($wantsOut && ! $wantsIn) {
                $query->whereNull('checked_in_at');
            }
        }

        if ($channels = $this->arrayFilter($request->input('filter_payment_channel'))) {
            $query->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->whereIn('payment_channel', $channels));
        }

        if ($modes = $this->arrayFilter($request->input('filter_mode'))) {
            $query->whereHas('ticketOrderItem.ticketOrder.paymentGateway', fn ($g) => $g->whereIn('mode', $modes));
        }

        if ($statuses = $this->arrayFilter($request->input('filter_order_status'))) {
            $query->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->whereIn('status', $statuses));
        }

        if ($ticketId = (int) $request->query('ticket_id')) {
            $query->where('ticket_id', $ticketId);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-id');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $fieldMap = [
            'id' => 'id',
            'name' => 'name',
            'checked_in_at' => 'checked_in_at',
            'created_at' => 'created_at',
            'deleted_at' => 'deleted_at',
        ];

        $query->orderBy($fieldMap[$field] ?? 'id', isset($fieldMap[$field]) ? $direction : 'desc');
    }

    /**
     * @return array<int, string>
     */
    private function arrayFilter(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_filter(is_array($value) ? $value : explode(',', (string) $value)));
    }
}
