<?php

use App\Jobs\Hotel\ReleaseExpiredAllotmentsJob;
use App\Jobs\Reservation\ExpireUnpaidReservationsJob;
use App\Jobs\Ticket\ExpireUnpaidTicketOrdersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune --hours=48')->daily();

Schedule::command('posts:publish-scheduled')->everyMinute();

// Prune temp uploads (FilePond) that were never attached to a model.
Schedule::command('uploads:cleanup-temp')->daily()->at('02:30');

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
