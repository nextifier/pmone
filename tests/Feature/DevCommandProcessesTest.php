<?php

use Illuminate\Foundation\DevCommands;

it('runs exactly the four processes local development needs', function () {
    $processes = collect(DevCommands::commands())
        ->pluck('command', 'name');

    expect($processes->keys()->all())->toEqualCanonicalizing([
        'server', 'horizon', 'frontend', 'tunnel',
    ]);

    expect($processes)
        ->get('server')->toContain('artisan serve')
        ->get('horizon')->toContain('artisan horizon')
        ->get('tunnel')->toContain('artisan db:tunnel');
});

it('runs the admin frontend from its own pnpm project', function () {
    $processes = collect(DevCommands::commands())->pluck('command', 'name');

    expect($processes->get('frontend'))->toBe('pnpm --dir frontend run dev');
});
