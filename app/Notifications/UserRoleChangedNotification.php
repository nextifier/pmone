<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UserRoleChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $newRoles
     */
    public function __construct(
        public array $newRoles,
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
        $rolesList = implode(', ', array_map('ucfirst', $this->newRoles));

        return [
            'title' => 'Role updated',
            'body' => "{$this->changedBy->name} updated your role to {$rolesList}",
            'icon' => 'hugeicons:shield-user',
            'url' => '/settings/profile',
        ];
    }
}
