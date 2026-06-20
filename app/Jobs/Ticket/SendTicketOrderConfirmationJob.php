<?php

namespace App\Jobs\Ticket;

use App\Mail\Ticket\TicketOrderConfirmationMail;
use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTicketOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $ticketOrderId) {}

    public function handle(): void
    {
        $order = TicketOrder::query()
            ->with(['event.project.links', 'items', 'attendees.ticket'])
            ->find($this->ticketOrderId);

        if (! $order || ! $order->buyer_email) {
            return;
        }

        $rawToken = TicketOrder::magicLinkTokenFor($order->order_number);
        $base = $order->event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');
        $magicLinkUrl = "{$base}/tickets/order/{$rawToken}";

        Mail::to($order->buyer_email)->send(new TicketOrderConfirmationMail($order, $magicLinkUrl));
    }
}
