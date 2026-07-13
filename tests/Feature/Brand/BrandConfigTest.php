<?php

it('sizes Horizon production supervisors from env with the historical defaults', function () {
    expect(config('horizon.environments.production.supervisor-1.maxProcesses'))->toBe(10)
        ->and(config('horizon.environments.production.supervisor-analytics.maxProcesses'))->toBe(3)
        ->and(config('horizon.environments.production.supervisor-pdf.maxProcesses'))->toBe(2)
        ->and(config('horizon.environments.production.supervisor-tickets.maxProcesses'))->toBe(10);
});

it('defaults brand config to the historical pmone identity', function () {
    expect(config('brand.support_email'))->toBe('support@pmone.id')
        ->and(config('brand.ics_domain'))->toBe('pmone.id');
});
