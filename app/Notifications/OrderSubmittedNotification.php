<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $brandName
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->order->loadMissing('brandEvent.event.project');
        $event = $this->order->brandEvent->event;

        return [
            'title' => 'New order received',
            'body' => "{$this->brandName} submitted order {$this->order->ulid}",
            'icon' => 'hugeicons:shopping-bag-01',
            'url' => "/projects/{$event->project->username}/events/{$event->slug}/orders/{$this->order->ulid}",
        ];
    }
}
