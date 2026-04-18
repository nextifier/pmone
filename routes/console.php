<?php

use App\Jobs\Hotel\ReleaseExpiredAllotmentsJob;
use App\Jobs\Reservation\ExpireUnpaidReservationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune --hours=48')->daily();

Schedule::command('posts:publish-scheduled')->everyMinute();

Schedule::command('activitylog:clean --force')->daily()->at('01:00')->environments(['production']);
Schedule::command('backup:clean')->daily()->at('02:00')->environments(['production']);
Schedule::command('backup:run --only-db')->daily()->at('03:00')->environments(['production']);
Schedule::command('backup:monitor')->daily()->at('04:00')->environments(['production']);

// Clean up temporary export files older than 1 hour
Schedule::call(function () {
    $directory = storage_path('app/tmp/exports');
    if (! is_dir($directory)) {
        return;
    }
    $files = glob($directory.'/*.xlsx');
    foreach ($files as $file) {
        if (filemtime($file) < now()->subHour()->timestamp) {
            unlink($file);
        }
    }
})->hourly();

// Hotel reservation scheduled jobs
Schedule::job(new ExpireUnpaidReservationsJob)->everyFifteenMinutes();
Schedule::job(new ReleaseExpiredAllotmentsJob)->dailyAt('00:30');
