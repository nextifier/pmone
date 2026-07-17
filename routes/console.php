<?php

use App\Jobs\Hotel\ReleaseExpiredAllotmentsJob;
use App\Jobs\Reservation\ExpireUnpaidReservationsJob;
use App\Jobs\Ticket\ExpireStaleWaitlistOffersJob;
use App\Jobs\Ticket\ExpireUnpaidTicketOrdersJob;
use App\Models\ApiConsumerRequest;
use App\Models\UserPageView;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune --hours=48')->daily();

Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping();

// Keep the local email history and delivery statuses in step with Resend.
Schedule::command('emails:sync-resend')->everyFifteenMinutes()->withoutOverlapping();

// Note: temp upload cleanup lives in bootstrap/app.php (hourly, --hours=1).
// A second daily registration here used the default 24h window and could never
// match anything the hourly run had not already deleted, so it was removed.

// Prune append-only history past its retention window (all environments).
Schedule::command('model:prune', ['--model' => [
    UserPageView::class,
    ApiConsumerRequest::class,
]])->dailyAt('01:30');

// Failed jobs and batches are stored in Postgres and were never pruned.
// Horizon's own trim settings only cover its Redis copy, not these tables.
Schedule::command('queue:prune-failed --hours=168')->daily()->at('01:10');
Schedule::command('queue:prune-batches --hours=168')->daily()->at('01:15');
Schedule::command('auth:clear-resets')->daily()->at('01:20');

// Horizon's metrics dashboard is fed by snapshots; without this it stays empty.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('activitylog:clean --force')->daily()->at('01:00')->environments(['production']);
Schedule::command('backup:clean')->daily()->at('02:00')->environments(['production']);
Schedule::command('backup:run --only-db')->daily()->at('03:00')->environments(['production']);
Schedule::command('backup:monitor')->daily()->at('04:00')->environments(['production']);

// Clean up temporary export artifacts older than 1 hour
Schedule::call(function () {
    $cutoff = now()->subHour()->timestamp;
    $patterns = [
        storage_path('app/tmp/exports').'/*.xlsx',
        storage_path('app/tmp/post-exports').'/*.zip',
        storage_path('app/tmp/post-exports').'/*.part',
        storage_path('app/tmp/post-exports').'/posts_export_*',
    ];
    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $path) {
            if (filemtime($path) >= $cutoff) {
                continue;
            }
            is_dir($path) ? File::deleteDirectory($path) : @unlink($path);
        }
    }
})->hourly();

// Hotel reservation scheduled jobs
Schedule::job(new ExpireUnpaidReservationsJob)->everyFifteenMinutes();
Schedule::job(new ReleaseExpiredAllotmentsJob)->dailyAt('00:30');

// Ticketing scheduled jobs
Schedule::job(new ExpireUnpaidTicketOrdersJob)->everyFifteenMinutes();
Schedule::job(new ExpireStaleWaitlistOffersJob)->everyFifteenMinutes();
