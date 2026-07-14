<?php

namespace App\Http\Controllers\Api;

use App\Enums\Ticketing\ScanAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scan\CheckInRequest;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventDay;
use App\Services\Ticket\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function __construct(protected ScanService $scans) {}

    public function checkIn(CheckInRequest $request, Event $event): JsonResponse
    {
        $action = ScanAction::from($request->input('action', ScanAction::CheckIn->value));

        $result = $this->scans->checkIn(
            $request->string('qr_token'),
            $event,
            $request->user()->id,
            $request->string('idempotency_key'),
            $action,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Display-only event context for the scanner shell header (poster, name,
     * days, venue). Gated by scan.check_in like the rest of this group, so a
     * scanner role that lacks events.view can still load it.
     */
    public function context(Event $event): JsonResponse
    {
        $event->loadMissing('media');

        return response()->json(['data' => [
            'id' => $event->id,
            'title' => $event->title,
            'date_label' => $event->date_label,
            'start_date' => $event->start_date?->toIso8601String(),
            'end_date' => $event->end_date?->toIso8601String(),
            'location' => $event->location,
            'hall' => $event->hall,
            'timezone' => $event->timezone,
            'poster_image' => $event->hasMedia('poster_image')
                ? $event->getMediaUrls('poster_image')
                : null,
            'days' => $event->eventDays->map(fn (EventDay $day): array => [
                'id' => $day->id,
                'day_number' => $day->day_number,
                'label' => $day->label,
                'date' => $day->date?->toDateString(),
            ])->all(),
        ]]);
    }

    public function search(Request $request, Event $event): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);

        return response()->json([
            'data' => $this->scans->search($event, $request->string('q')),
        ]);
    }

    public function manualCheckIn(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'attendee_ulid' => ['required', 'string'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $attendee = Attendee::query()->where('ulid', $validated['attendee_ulid'])->firstOrFail();

        $result = $this->scans->checkIn(
            $attendee->qr_token,
            $event,
            $request->user()->id,
            $validated['idempotency_key'],
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Offline manifest of admissible attendees. Two modes on the same route:
     *  - Default (no cursor/limit/paged param): the full manifest - unchanged
     *    for existing small-event scanners.
     *  - Paged (opt-in via cursor/limit/paged): one keyset page + `next_cursor`
     *    + a `version` timestamp. A very large event pages once, then pulls only
     *    deltas from manifest/changes?since=version (plan 022). The device keeps
     *    the FIRST page's `version` as its delta floor.
     */
    public function manifest(Request $request, Event $event): JsonResponse
    {
        if (! $request->has('cursor') && ! $request->has('limit') && ! $request->boolean('paged')) {
            return response()->json([
                'data' => $this->scans->manifest($event),
                'generated_at' => now()->toIso8601String(),
            ]);
        }

        $validated = $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        // Captured before the query so the delta floor never sits after a row
        // this page might not have seen; a small overlap only re-pulls (the
        // client merge is idempotent), a gap would drop a change.
        $version = now()->toIso8601String();

        $page = $this->scans->manifestPage(
            $event,
            $validated['cursor'] ?? null,
            $validated['limit'] ?? 1000,
        );

        return response()->json([
            'data' => $page['data'],
            'next_cursor' => $page['next_cursor'],
            'version' => $version,
            'generated_at' => $version,
        ]);
    }

    /**
     * Manifest deltas since a timestamp cursor (plan 022): attendees added,
     * removed, or qr-rotated since `since`, each tagged upsert/remove so the
     * device patches its cached manifest instead of re-pulling everything.
     */
    public function manifestChanges(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['required', 'date'],
        ]);

        $version = now()->toIso8601String();

        return response()->json([
            'data' => $this->scans->manifestChangesSince($event, $validated['since']),
            'version' => $version,
            'generated_at' => $version,
        ]);
    }

    /**
     * Offline sync: push a batch of queued scans (idempotent by client UUID),
     * then pull check-ins recorded by other devices since the cursor.
     */
    public function sync(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            // The client chunks its outbox into <=200-entry slices before
            // posting (frontend `SYNC_CHUNK`), so a large offline batch syncs
            // across several requests instead of 422ing as one oversized call.
            'logs' => ['sometimes', 'array', 'max:200'],
            'logs.*.qr_token' => ['required_with:logs', 'string'],
            'logs.*.idempotency_key' => ['required_with:logs', 'string', 'max:64'],
            'logs.*.action' => ['sometimes', 'string'],
            'logs.*.scanned_at' => ['sometimes', 'nullable', 'date'],
            'cursor' => ['nullable', 'date'],
        ]);

        $applied = [];
        foreach ($validated['logs'] ?? [] as $log) {
            $action = ScanAction::tryFrom($log['action'] ?? 'check_in') ?? ScanAction::CheckIn;
            $applied[] = [
                'idempotency_key' => $log['idempotency_key'],
                'result' => $this->scans->checkIn(
                    $log['qr_token'],
                    $event,
                    $request->user()->id,
                    $log['idempotency_key'],
                    $action,
                    $log['scanned_at'] ?? null,
                )['result'],
            ];
        }

        return response()->json([
            'applied' => $applied,
            'pull' => $this->scans->checkInsSince($event, $validated['cursor'] ?? null),
            'cursor' => now()->toIso8601String(),
        ]);
    }
}
