<?php

namespace App\Notifications;

use App\Models\FormResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FormResponseReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FormResponse $response
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
        $this->response->loadMissing('form');
        $form = $this->response->form;
        $email = $this->response->respondent_email;

        return [
            'title' => 'New form response',
            'body' => $email
                ? "{$form->title} received a response from {$email}"
                : "{$form->title} received a new response",
            'icon' => 'hugeicons:note-edit',
            'url' => "/forms/{$form->slug}/responses",
        ];
    }
}
