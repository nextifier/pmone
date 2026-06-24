<?php

it('refuses to run in the production environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:pull-production', ['--yes' => true])
        ->expectsOutputToContain('Refusing to run db:pull-production')
        ->assertExitCode(1);
});

it('refuses when local and production resolve to the same endpoint', function () {
    config()->set('database.connections.pgsql.host', '127.0.0.1');
    config()->set('database.connections.pgsql.port', '5433');
    config()->set('database.connections.pgsql_production.host', '127.0.0.1');
    config()->set('database.connections.pgsql_production.port', '5433');

    $this->artisan('db:pull-production', ['--yes' => true])
        ->expectsOutputToContain('same host:port')
        ->assertExitCode(1);
});
