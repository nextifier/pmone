<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnalyticsSyncFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $failureDetails
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $failCount = $this->failureDetails['fail_count'] ?? 0;
        $successCount = $this->failureDetails['success_count'] ?? 0;
        $totalProperties = $this->failureDetails['total_properties'] ?? 0;
        $successRate = $this->failureDetails['success_rate'] ?? '0%';

        return (new MailMessage)
            ->error()
            ->subject('Analytics Sync Failed - Immediate Attention Required')
            ->line("Analytics cache refresh completed with {$failCount} failure(s).")
            ->line("Success: {$successCount}/{$totalProperties} properties ({$successRate})")
            ->action('View Sync Logs', url('/admin/google-analytics-properties'))
            ->line('Please investigate the failures to ensure accurate analytics data.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $failCount = $this->failureDetails['fail_count'] ?? 0;
        $successRate = $this->failureDetails['success_rate'] ?? '0%';

        return [
            'title' => 'Analytics sync failed',
            'body' => "Analytics sync completed with {$failCount} failure(s) - {$successRate} success rate",
            'icon' => 'hugeicons:chart-breakout-square',
            'url' => '/web-analytics/sync-history',
        ];
    }
}
