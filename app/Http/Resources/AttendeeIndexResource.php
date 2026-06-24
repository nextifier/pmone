<?php

namespace App\Http\Resources;

use App\Models\Attendee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin list shape for the Attendees table (mirrors ReservationIndexResource).
 * Surfaces the parent ticket order's payment channel, gateway mode/provider,
 * status and totals so the table can show Payment / Mode / Created columns and
 * link to the per-order invoice / receipt PDFs.
 *
 * @mixin Attendee
 */
class AttendeeIndexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->ticketOrderItem?->ticketOrder;
        $isFree = $order ? $order->isFree() : false;
        $canViewDocuments = (bool) auth()->user()?->can('attendees.view_documents');

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'qr_token' => $this->qr_token,
            'is_personalized' => $this->personalized_at !== null,
            'is_checked_in' => $this->checked_in_at !== null,
            'has_account' => $this->claimed_by_user_id !== null,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_in_by_name' => $this->whenLoaded('checkedInBy', fn () => $this->checkedInBy?->name),
            'reprint_count' => $this->reprint_count,
            'ticket' => $this->whenLoaded('ticket', fn () => [
                'id' => $this->ticket->id,
                'slug' => $this->ticket->slug,
                'kind' => $this->ticket->kind?->value,
                'title' => $this->ticket->getTranslation('title', app()->getLocale(), false),
                'tier' => $this->ticket->tier,
            ]),
            'day' => $this->whenLoaded('ticketOrderItem', fn () => $this->ticketOrderItem->selectedEventDay ? [
                'id' => $this->ticketOrderItem->selectedEventDay->id,
                'label' => $this->ticketOrderItem->selectedEventDay->label,
                'date' => $this->ticketOrderItem->selectedEventDay->date?->toDateString(),
            ] : null),
            'session' => $this->whenLoaded('ticketOrderItem', fn () => $this->ticketOrderItem->ticketSession ? [
                'id' => $this->ticketOrderItem->ticketSession->id,
                'label' => $this->ticketOrderItem->ticketSession->label,
            ] : null),
            'order' => $order ? [
                'number' => $order->order_number,
                'ulid' => $order->ulid,
                'status' => $order->status?->value,
                'status_label' => $order->status?->label(),
                'status_color' => $order->status?->color(),
                'source' => $order->source,
            ] : null,
            'payment_channel' => $order?->payment_channel,
            'payment_mode' => $order?->paymentGateway?->mode,
            'payment_provider' => $order?->paymentGateway?->provider,
            'marked_paid_manually' => $order?->marked_paid_manually_at !== null,
            'marked_paid_at' => $order?->marked_paid_manually_at?->toIso8601String(),
            'marked_paid_by_name' => $order?->relationLoaded('markedPaidBy') ? $order->markedPaidBy?->name : null,
            'total_amount' => $order ? (float) $order->total : null,
            'paid_at' => $order?->paid_at?->toIso8601String(),
            'is_free' => $isFree,
            'can_delete' => (bool) auth()->user()?->can('attendees.delete'),
            'can_view_documents' => $canViewDocuments && $order !== null && ! $isFree,
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
