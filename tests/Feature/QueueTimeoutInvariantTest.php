<?php

use App\Jobs\Ticket\GenerateBulkAttendeesJob;

/**
 * A queue connection's retry_after must stay above the running time of anything
 * that consumes it. If it drops below, Redis hands a still-running job to a
 * second worker: for a tries=1 destructive job such as BulkDeleteMedia that
 * means two concurrent delete passes over the same records.
 *
 * Production ran with retry_after=90 against supervisors timing out at 600.
 * These tests exist so that cannot silently come back.
 */
it('gives every horizon supervisor a retry_after above its timeout', function () {
    $supervisors = config('horizon.defaults');
    expect($supervisors)->not->toBeEmpty();

    foreach ($supervisors as $name => $config) {
        $retryAfter = config("queue.connections.{$config['connection']}.retry_after");

        expect($retryAfter)->toBeGreaterThan(
            $config['timeout'],
            "Supervisor {$name} times out at {$config['timeout']}s but its ".
            "'{$config['connection']}' connection re-reserves jobs after {$retryAfter}s."
        );
    }
});

it('runs the bulk attendee job on a supervisor that neither kills nor re-reserves it early', function () {
    $job = new GenerateBulkAttendeesJob(1, []);

    // The job pins only its queue, so it still runs inline under the sync driver.
    // Re-reservation is decided by the connection the worker pulls with.
    expect($job->queue)->toBe('bulk');

    $supervisor = collect(config('horizon.defaults'))
        ->first(fn (array $config) => in_array($job->queue, $config['queue'], true));
    expect($supervisor)->not->toBeNull();

    $retryAfter = config("queue.connections.{$supervisor['connection']}.retry_after");

    expect($supervisor['timeout'])->toBeGreaterThanOrEqual($job->timeout)
        ->and($retryAfter)->toBeGreaterThan($job->timeout);
});

it('pushes and consumes the bulk queue over the same redis connection', function () {
    // supervisor-bulk pulls `bulk` over redis-long while dispatches land on the
    // default redis connection. Both must point at the same Redis connection, or
    // the job is pushed to a list nothing is reading.
    expect(config('queue.connections.redis-long.connection'))
        ->toBe(config('queue.connections.redis.connection'));
});

/**
 * Horizon's process ceilings are a memory budget shared with PHP-FPM and
 * PostgreSQL on the same box, not a throughput dial. They summed to 26 while the
 * app server could hold roughly 10 workers; that only stayed harmless because
 * the queues were idle. See the sizing note in config/horizon.php.
 */
it('keeps the production worker ceiling inside the app server memory budget', function () {
    $ceiling = collect(config('horizon.environments.production'))
        ->sum(fn (array $supervisor) => $supervisor['maxProcesses']);

    expect($ceiling)->toBeLessThanOrEqual(
        12,
        "Horizon may run up to {$ceiling} workers at once. The 4GB app server has ".
        'room for ~10 alongside PHP-FPM and PostgreSQL, and the OOM killer takes '.
        'PostgreSQL first. Raise this only together with more RAM.'
    );
});

it('gives ticket checkout the largest share of the worker ceiling', function () {
    $production = config('horizon.environments.production');
    $tickets = $production['supervisor-tickets']['maxProcesses'];

    // On an event day the tickets queue is the one that must not fall behind:
    // checkout is a POST, so Cloudflare cannot absorb any of it.
    foreach ($production as $name => $supervisor) {
        if ($name === 'supervisor-tickets') {
            continue;
        }

        expect($tickets)->toBeGreaterThan(
            $supervisor['maxProcesses'],
            "supervisor-tickets ({$tickets}) must outrank {$name} ({$supervisor['maxProcesses']})."
        );
    }
});
