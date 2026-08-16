<?php

// Two separate promises. The fallbacks are deliberately modest so a box that
// forgets to set the env vars stays within its memory budget rather than
// spawning 25 workers; the real production numbers live in .env.example and
// arrive through the environment. Commit e79c98ff lowered the fallbacks and
// left this test asserting the old ones.
it('falls back to conservative Horizon supervisor sizes when no env is set', function () {
    expect(config('horizon.environments.production.supervisor-1.maxProcesses'))->toBe(3)
        ->and(config('horizon.environments.production.supervisor-analytics.maxProcesses'))->toBe(1)
        ->and(config('horizon.environments.production.supervisor-pdf.maxProcesses'))->toBe(1)
        ->and(config('horizon.environments.production.supervisor-tickets.maxProcesses'))->toBe(6)
        ->and(config('horizon.environments.production.supervisor-bulk.maxProcesses'))->toBe(1);
});

it('sizes Horizon production supervisors from env when it is set', function () {
    $envKeys = [
        'supervisor-1' => 'HORIZON_DEFAULT_MAX_PROCESSES',
        'supervisor-analytics' => 'HORIZON_ANALYTICS_MAX_PROCESSES',
        'supervisor-pdf' => 'HORIZON_PDF_MAX_PROCESSES',
        'supervisor-tickets' => 'HORIZON_TICKETS_MAX_PROCESSES',
        'supervisor-bulk' => 'HORIZON_BULK_MAX_PROCESSES',
    ];

    foreach ($envKeys as $key) {
        putenv("{$key}=7");
        $_ENV[$key] = '7';
    }

    // Config is cached per boot, so re-read the file rather than the container.
    $horizon = require config_path('horizon.php');

    foreach ($envKeys as $supervisor => $key) {
        expect($horizon['environments']['production'][$supervisor]['maxProcesses'])
            ->toBe(7, "{$supervisor} ignored {$key}");

        putenv($key);
        unset($_ENV[$key]);
    }
});

it('defaults brand config to the historical pmone identity', function () {
    expect(config('brand.support_email'))->toBe('support@pmone.id')
        ->and(config('brand.ics_domain'))->toBe('pmone.id');
});
