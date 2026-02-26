<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $newStatus,
        public User $changedBy
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
        $statusLabels = [
            'confirmed' => 'confirmed',
            'processing' => 'being processed',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'submitted' => 'resubmitted',
        ];

        $label = $statusLabels[$this->newStatus] ?? $this->newStatus;

        return [
            'title' => 'Order status updated',
            'body' => "Your order {$this->order->order_number} has been {$label}",
            'icon' => 'hugeicons:shopping-bag-01',
            'url' => "/orders/{$this->order->ulid}",
        ];
    }
}
