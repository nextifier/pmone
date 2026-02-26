<?php

namespace App\Notifications;

use App\Models\Brand;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BrandInvitedToEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Brand $brand,
        public Event $event
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
        return [
            'title' => 'Brand added to event',
            'body' => "\"{$this->brand->name}\" has been added to \"{$this->event->title}\"",
            'icon' => 'hugeicons:calendar-03',
            'url' => "/brands/{$this->brand->slug}",
        ];
    }
}
