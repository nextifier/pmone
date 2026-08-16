<?php

beforeEach(function () {
    config()->set('database.tunnel', [
        'host' => 'db.example.test',
        'username' => 'forge',
        'identity_file' => '~/.ssh/tunnel_key',
        'local_port' => '5433',
        'remote_host' => '127.0.0.1',
        'remote_port' => '5432',
    ]);
});

it('builds the ssh port-forward command from config', function () {
    $home = rtrim((string) getenv('HOME'), '/');

    $this->artisan('db:tunnel --dry-run')
        ->expectsOutputToContain(
            "ssh -N -i {$home}/.ssh/tunnel_key -L 5433:127.0.0.1:5432"
            .' -o ExitOnForwardFailure=yes -o ServerAliveInterval=60 -o ServerAliveCountMax=3'
            .' forge@db.example.test'
        )
        ->assertSuccessful();
});

it('omits the identity flag when no key is configured', function () {
    config()->set('database.tunnel.identity_file', null);

    $this->artisan('db:tunnel --dry-run')
        ->expectsOutputToContain('ssh -N -L 5433:127.0.0.1:5432')
        ->assertSuccessful();
});

it('leaves an absolute identity path untouched', function () {
    config()->set('database.tunnel.identity_file', '/etc/ssh/tunnel_key');

    $this->artisan('db:tunnel --dry-run')
        ->expectsOutputToContain('-i /etc/ssh/tunnel_key')
        ->assertSuccessful();
});

it('fails with setup instructions when no host is configured', function () {
    config()->set('database.tunnel.host', null);

    $this->artisan('db:tunnel')
        ->expectsOutputToContain('No tunnel host configured.')
        ->assertFailed();
});

it('does nothing when the local port already answers', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $port = (int) explode(':', stream_socket_get_name($server, false))[1];

    config()->set('database.tunnel.local_port', (string) $port);

    try {
        $this->artisan('db:tunnel')
            ->expectsOutputToContain("Tunnel already up on 127.0.0.1:{$port}")
            ->assertSuccessful();
    } finally {
        fclose($server);
    }
});
