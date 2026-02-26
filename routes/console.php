<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune --hours=48')->daily();

Schedule::command('posts:publish-scheduled')->everyMinute();

Schedule::command('backup:clean')->daily()->at('02:00')->environments(['production']);
Schedule::command('backup:run --only-db')->daily()->at('03:00')->environments(['production']);
Schedule::command('backup:monitor')->daily()->at('04:00')->environments(['production']);
