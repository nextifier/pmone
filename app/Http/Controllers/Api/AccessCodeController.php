<?php

namespace App\Http\Controllers\Api;

use App\Exports\AccessCodesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessCode\StoreAccessCodeBatchRequest;
use App\Http\Requests\AccessCode\UpdateAccessCodeRequest;
use App\Http\Resources\AccessCodeBatchResource;
use App\Http\Resources\AccessCodeIndexResource;
use App\Http\Resources\AccessCodeRedemptionResource;
use App\Http\Resources\AccessCodeResource;
use App\Jobs\Ticket\SendAccessCodeInviteJob;
use App\Models\AccessCode;
use App\Models\Event;
use App\Services\Ticket\AccessCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccessCodeController extends Controller
{
    public function __construct(protected AccessCodeService $accessCodes) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $query = $event->accessCodes()
            ->with('batch:id,ulid,name,assigned_to')
            ->withCount(['unlocks', 'redemptions']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('code', $like, "%{$search}%")
                    ->orWhere('bind_email', $like, "%{$search}%")
                    ->orWhere('bind_phone', $like, "%{$search}%");
            });
        }

        if ($request->filled('kind')) {
            $query->where('kind', $request->input('kind'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('batch_ulid')) {
            $query->whereHas('batch', fn ($q) => $q->where('ulid', $request->input('batch_ulid')));
        }

        $items = $query->orderByDesc('created_at')->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'data' => AccessCodeIndexResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(StoreAccessCodeBatchRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();

        $batch = $this->accessCodes->generateBatch($event, $data);

        if (($data['delivery'] ?? 'none') === 'send_invites') {
            DB::afterCommit(function () use ($batch) {
                foreach ($batch->accessCodes as $code) {
                    if ($code->bind_email || $code->bind_phone) {
                        SendAccessCodeInviteJob::dispatch($code->id);
                    }
                }
            });
        }

        return response()->json([
            'message' => "Generated {$batch->accessCodes->count()} access code(s)",
            'data' => (new AccessCodeBatchResource($batch->loadCount('accessCodes')))->resolve(),
        ], 201);
    }

    public function show(Event $event, AccessCode $accessCode): JsonResponse
    {
        $accessCode->load(['batch', 'unlocks:id,slug,title'])->loadCount('redemptions');

        return response()->json([
            'data' => (new AccessCodeResource($accessCode))->resolve(),
        ]);
    }

    public function update(UpdateAccessCodeRequest $request, Event $event, AccessCode $accessCode): JsonResponse
    {
        $data = $request->validated();
        $unlocks = $data['unlocks'] ?? null;
        unset($data['unlocks']);

        $accessCode->update($data);

        if ($unlocks !== null) {
            $accessCode->unlocks()->sync($unlocks);
        }

        return response()->json([
            'message' => 'Access code updated successfully',
            'data' => (new AccessCodeResource($accessCode->fresh(['batch', 'unlocks'])))->resolve(),
        ]);
    }

    public function destroy(Event $event, AccessCode $accessCode): JsonResponse
    {
        $accessCode->delete();

        return response()->json(['message' => 'Access code deleted']);
    }

    public function revoke(Request $request, Event $event, AccessCode $accessCode): JsonResponse
    {
        $this->accessCodes->revoke($accessCode, (string) $request->input('reason', ''));

        return response()->json([
            'message' => 'Access code revoked',
            'data' => (new AccessCodeResource($accessCode->fresh()))->resolve(),
        ]);
    }

    public function redemptions(Request $request, Event $event, AccessCode $accessCode): JsonResponse
    {
        $items = $accessCode->redemptions()
            ->with('ticketOrder:id,ulid,order_number,status')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'data' => AccessCodeRedemptionResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function sendInvite(Event $event, AccessCode $accessCode): JsonResponse
    {
        abort_if(! $accessCode->bind_email && ! $accessCode->bind_phone, 422, 'This code has no bound email or phone to send to.');

        SendAccessCodeInviteJob::dispatch($accessCode->id);

        return response()->json(['message' => 'Invite queued for delivery']);
    }

    public function export(Request $request, Event $event): BinaryFileResponse
    {
        $filename = 'access_codes_'.$event->slug.'_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties(['model_type' => 'AccessCode', 'event_id' => $event->id, 'filename' => $filename])
            ->log('Exported access codes');

        return Excel::download(new AccessCodesExport($event->id, [
            'search' => $request->input('search'),
            'kind' => $request->input('kind'),
            'status' => $request->input('status'),
        ]), $filename);
    }
}
