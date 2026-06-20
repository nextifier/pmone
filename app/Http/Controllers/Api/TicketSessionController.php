<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketSession\StoreTicketSessionRequest;
use App\Http\Requests\TicketSession\UpdateTicketSessionRequest;
use App\Http\Resources\TicketSessionResource;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketSessionController extends Controller
{
    public function index(Event $event, Ticket $ticket): JsonResponse
    {
        $sessions = $ticket->sessions()->orderBy('order_column')->get();

        return response()->json([
            'data' => TicketSessionResource::collection($sessions),
            'meta' => ['total' => $sessions->count()],
        ]);
    }

    public function store(StoreTicketSessionRequest $request, Event $event, Ticket $ticket): JsonResponse
    {
        $session = $ticket->sessions()->create($request->validated());

        return response()->json([
            'message' => 'Session created successfully',
            'data' => new TicketSessionResource($session),
        ], 201);
    }

    public function show(Event $event, Ticket $ticket, TicketSession $session): JsonResponse
    {
        return response()->json(['data' => new TicketSessionResource($session)]);
    }

    public function update(UpdateTicketSessionRequest $request, Event $event, Ticket $ticket, TicketSession $session): JsonResponse
    {
        $session->update($request->validated());

        return response()->json([
            'message' => 'Session updated successfully',
            'data' => new TicketSessionResource($session->fresh()),
        ]);
    }

    public function destroy(Event $event, Ticket $ticket, TicketSession $session): JsonResponse
    {
        $session->delete();

        return response()->json(['message' => 'Session deleted successfully']);
    }

    public function reorder(Request $request, Event $event, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        if ($ticket->sessions()->whereIn('id', $ids)->count() !== count($ids)) {
            return response()->json(['message' => 'One or more sessions do not belong to this ticket.'], 422);
        }

        DB::transaction(function () use ($validated, $ticket) {
            foreach ($validated['orders'] as $order) {
                $ticket->sessions()->where('id', $order['id'])->update(['order_column' => $order['order']]);
            }
        });

        return response()->json(['message' => 'Session order updated successfully']);
    }
}
