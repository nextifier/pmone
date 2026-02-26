<?php

namespace App\Notifications;

use App\Models\BrandEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PromotionPostUploadedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BrandEvent $brandEvent,
        public User $uploadedBy
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
        $brandName = $this->brandEvent->brand?->name ?? 'A brand';
        $eventTitle = $this->brandEvent->event?->title ?? 'an event';

        return [
            'title' => 'New promotion post',
            'body' => "{$this->uploadedBy->name} uploaded a promotion post for \"{$brandName}\" in \"{$eventTitle}\"",
            'icon' => 'hugeicons:image-02',
            'url' => "/brands/{$this->brandEvent->brand?->slug}/promotion-posts/{$this->brandEvent->id}",
        ];
    }
}
