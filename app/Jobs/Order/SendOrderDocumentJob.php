<?php

namespace App\Jobs\Order;

use App\Mail\Order\OrderDocumentMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  'invoice'|'receipt'  $type
     */
    public function __construct(public int $orderId, public string $type) {}

    public function handle(): void
    {
        $order = Order::query()
            ->with(['items', 'brandEvent.brand.users', 'brandEvent.event', 'media'])
            ->find($this->orderId);

        if (! $order || ! $order->hasMedia($this->type)) {
            return;
        }

        $brand = $order->brandEvent?->brand;

        if (! $brand) {
            return;
        }

        $recipients = $brand->recipientEmails();

        foreach ($recipients as $email) {
            Mail::to($email)->send(new OrderDocumentMail($order, $this->type));
        }
    }
}
