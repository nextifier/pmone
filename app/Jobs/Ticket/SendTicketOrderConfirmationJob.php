<?php

namespace App\Jobs\Ticket;

use App\Mail\Ticket\TicketOrderConfirmationMail;
use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $ticketOrderId)
    {
        $this->onQueue('tickets');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Failed to send ticket order confirmation email', [
            'ticket_order_id' => $this->ticketOrderId,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(): void
    {
        $order = TicketOrder::query()
            ->with(['event.project.media', 'event.project.links', 'items.ticket', 'attendees.ticket'])
            ->find($this->ticketOrderId);

        if (! $order || ! $order->buyer_email) {
            return;
        }

        Mail::to($order->buyer_email)->send(TicketOrderConfirmationMail::for($order));
    }
}
