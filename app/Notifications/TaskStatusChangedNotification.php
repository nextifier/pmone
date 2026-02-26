<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
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
            'todo' => 'to do',
            'in_progress' => 'in progress',
            'in_review' => 'in review',
            'done' => 'done',
            'cancelled' => 'cancelled',
        ];

        $label = $statusLabels[$this->newStatus] ?? $this->newStatus;

        return [
            'title' => 'Task status updated',
            'body' => "{$this->changedBy->name} marked \"{$this->task->title}\" as {$label}",
            'icon' => 'hugeicons:task-daily-01',
            'url' => "/tasks?ulid={$this->task->ulid}",
        ];
    }
}
