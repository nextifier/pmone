<?php

namespace App\Exports;

use App\Models\Attendee;
use App\Models\TicketOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Every attendee in an admin bulk-generate batch, with its shareable e-ticket
 * link - so staff can distribute the comp tickets.
 */
class BulkTicketBatchExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?string $base = null;

    public function __construct(protected TicketOrder $order) {}

    /**
     * Public base URL for the batch's event, resolved once (avoids an N+1 across
     * the mapped rows). Prefers the event's Website URL, falls back to the
     * configured frontend URL.
     */
    protected function base(): string
    {
        if ($this->base === null) {
            $this->order->loadMissing('event.project.links');
            $this->base = $this->order->event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');
        }

        return $this->base;
    }

    public function collection()
    {
        return Attendee::query()
            ->whereHas('ticketOrderItem', fn ($q) => $q->where('ticket_order_id', $this->order->id))
            ->with('ticket')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Name', 'Email', 'Ticket', 'QR Token', 'E-Ticket URL', 'Checked In'];
    }

    /**
     * @param  Attendee  $attendee
     * @return array<int, mixed>
     */
    public function map($attendee): array
    {
        return [
            $attendee->name,
            $attendee->email,
            $attendee->ticket?->title,
            $attendee->qr_token,
            "{$this->base()}/tickets/{$attendee->ulid}",
            $attendee->checked_in_at ? 'Yes' : 'No',
        ];
    }
}
