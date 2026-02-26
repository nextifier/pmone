<?php

namespace App\Notifications;

use App\Models\ContactFormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewInboxMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ContactFormSubmission $submission
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
        $senderName = $this->submission->form_data['name'] ?? 'Someone';
        $subject = $this->submission->subject ?? 'No subject';

        return [
            'title' => 'New inquiry received',
            'body' => "{$senderName} sent: \"{$subject}\"",
            'icon' => 'hugeicons:mail-open-love',
            'url' => "/inbox/{$this->submission->ulid}",
        ];
    }
}
