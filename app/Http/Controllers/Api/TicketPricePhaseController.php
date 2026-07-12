<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketPricePhase\StoreTicketPricePhaseRequest;
use App\Http\Requests\TicketPricePhase\UpdateTicketPricePhaseRequest;
use App\Http\Resources\TicketPricePhaseResource;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;

class TicketPricePhaseController extends Controller
{
    public function index(Event $event, Ticket $ticket): JsonResponse
    {
        $phases = $ticket->pricePhases()->orderBy('order_column')->get();

        return response()->json([
            'data' => TicketPricePhaseResource::collection($phases),
            'meta' => ['total' => $phases->count()],
        ]);
    }

    public function store(StoreTicketPricePhaseRequest $request, Event $event, Ticket $ticket): JsonResponse
    {
        $phase = $ticket->pricePhases()->create($request->validated());

        return response()->json([
            'message' => 'Price phase created successfully',
            'data' => new TicketPricePhaseResource($phase),
        ], 201);
    }

    public function show(Event $event, Ticket $ticket, TicketPricePhase $pricePhase): JsonResponse
    {
        return response()->json(['data' => new TicketPricePhaseResource($pricePhase)]);
    }

    public function update(UpdateTicketPricePhaseRequest $request, Event $event, Ticket $ticket, TicketPricePhase $pricePhase): JsonResponse
    {
        $pricePhase->update($request->validated());

        return response()->json([
            'message' => 'Price phase updated successfully',
            'data' => new TicketPricePhaseResource($pricePhase->fresh()),
        ]);
    }

    public function destroy(Event $event, Ticket $ticket, TicketPricePhase $pricePhase): JsonResponse
    {
        $pricePhase->delete();

        return response()->json(['message' => 'Price phase deleted successfully']);
    }

    public function reorder(Request $request, Event $event, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        if ($ticket->pricePhases()->whereIn('id', $ids)->count() !== count($ids)) {
            return response()->json(['message' => 'One or more phases do not belong to this ticket.'], 422);
        }

        DB::transaction(function () use ($validated, $ticket) {
            foreach ($validated['orders'] as $order) {
                $ticket->pricePhases()->where('id', $order['id'])->update(['order_column' => $order['order']]);
            }
        });

        // Bulk query-builder updates skip model events, so bust the cache manually.
        ResponseCache::clear(['tickets']);

        return response()->json(['message' => 'Price phase order updated successfully']);
    }
}
