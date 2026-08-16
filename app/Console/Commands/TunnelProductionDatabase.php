<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Opens the SSH tunnel that exposes the PRODUCTION database on a local port -
 * the same port `db:pull-production` and the local Postgres tooling connect
 * through.
 *
 * The command replaces its own process with ssh (pcntl_exec) instead of
 * spawning a child. Whatever signals the terminal - or `php artisan dev` -
 * sends then land on ssh directly, so quitting can never leave an orphaned
 * tunnel behind holding the local port.
 */
class TunnelProductionDatabase extends Command
{
    protected $signature = 'db:tunnel
        {--dry-run : Print the ssh command instead of running it}';

    protected $description = 'Open the SSH tunnel to the production database.';

    public function handle(): int
    {
        /** @var array<string,mixed> $tunnel */
        $tunnel = config('database.tunnel');

        if (blank($tunnel['host'])) {
            $this->error('No tunnel host configured.');
            $this->line('  Set DB_TUNNEL_HOST, DB_TUNNEL_USERNAME and DB_TUNNEL_IDENTITY_FILE in your .env.');

            return self::FAILURE;
        }

        $arguments = $this->sshArguments($tunnel);

        if ($this->option('dry-run')) {
            $this->line('ssh '.implode(' ', $arguments));

            return self::SUCCESS;
        }

        $localPort = (int) $tunnel['local_port'];

        if ($this->tunnelIsUp($localPort)) {
            $this->info("Tunnel already up on 127.0.0.1:{$localPort}, nothing to do.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Tunnelling 127.0.0.1:%d -> %s:%s via %s@%s',
            $localPort,
            $tunnel['remote_host'],
            $tunnel['remote_port'],
            $tunnel['username'],
            $tunnel['host'],
        ));

        if (extension_loaded('pcntl')) {
            pcntl_exec('/usr/bin/env', ['ssh', ...$arguments]);
        }

        passthru('ssh '.implode(' ', array_map(escapeshellarg(...), $arguments)), $exitCode);

        return $exitCode;
    }

    /**
     * @param  array<string,mixed>  $tunnel
     * @return list<string>
     */
    private function sshArguments(array $tunnel): array
    {
        $identity = filled($tunnel['identity_file'])
            ? ['-i', $this->expandHome((string) $tunnel['identity_file'])]
            : [];

        return [
            '-N',
            ...$identity,
            '-L', sprintf('%s:%s:%s', $tunnel['local_port'], $tunnel['remote_host'], $tunnel['remote_port']),
            // Refuse to sit there connected but forwarding nothing when the local
            // port is already taken, and notice a dead link within ~3 minutes so
            // `php artisan dev` can restart the process.
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ServerAliveInterval=60',
            '-o', 'ServerAliveCountMax=3',
            sprintf('%s@%s', $tunnel['username'], $tunnel['host']),
        ];
    }

    private function expandHome(string $path): string
    {
        if (! str_starts_with($path, '~/')) {
            return $path;
        }

        return rtrim((string) (getenv('HOME') ?: ''), '/').substr($path, 1);
    }

    private function tunnelIsUp(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
