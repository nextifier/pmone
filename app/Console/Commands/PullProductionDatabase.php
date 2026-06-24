<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Clones the PRODUCTION database into the LOCAL one in a single command.
 *
 * Bakes in every lesson from the manual sync:
 *  - "Slim" dump: structure for all tables, but data skipped for the huge
 *    log/analytics/ephemeral tables (saves ~3.4 GB, dump ≈ tens of MB).
 *  - Full pg_dump/pg_restore so sequences (setval) are preserved, avoiding
 *    duplicate-key drift on the next insert.
 *  - Auto-detects a pg_dump/pg_restore new enough for the production server
 *    (newest installed wins: DBngin, Homebrew, or Postgres.app).
 *  - Never downloads media: records keep disk='r2', images stream from the CDN.
 *
 * Safety: refuses to run in the production environment, and refuses if the
 * local target resolves to the same host:port as production. Confirms before
 * wiping the local database (skip with --yes).
 *
 * Requires the SSH tunnel to production (default 127.0.0.1:5433) to be up.
 */
class PullProductionDatabase extends Command
{
    protected $signature = 'db:pull-production
        {--yes : Skip the confirmation prompt}
        {--dry-run : Show the plan without changing anything}
        {--keep-dump : Keep the dump file instead of deleting it afterwards}
        {--fresh-password= : After restore, set this password on the --fresh-email accounts (for local login)}
        {--fresh-email=* : Email(s) to apply --fresh-password to}
        {--prune-media : Also delete orphaned local media under storage/app/public (frees disk)}';

    protected $description = 'Clone the production database into local (slim dump, sequences intact, media stays on the CDN).';

    /**
     * Tables whose DATA is skipped (structure is still created). Logs, analytics
     * and ephemeral tables that are large and pointless in a dev clone.
     *
     * @var list<string>
     */
    private const EXCLUDE_TABLE_DATA = [
        'api_consumer_requests',
        'visits',
        'clicks',
        'analytics_metrics',
        'analytics_sync_logs',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        // ── Guard 1: never operate from a production app ──────────────────
        if (app()->environment('production')) {
            $this->error('Refusing to run db:pull-production in the production environment.');

            return self::FAILURE;
        }

        /** @var array<string,mixed> $local */
        $local = config('database.connections.pgsql');
        /** @var array<string,mixed> $prod */
        $prod = config('database.connections.pgsql_production');

        // ── Guard 2: local target must not BE production ──────────────────
        if ($local['host'] === $prod['host'] && (string) $local['port'] === (string) $prod['port']) {
            $this->error('Local and production point to the same host:port, aborting to avoid overwriting production.');

            return self::FAILURE;
        }

        // ── Locate pg_dump/pg_restore new enough for the prod server ──────
        $bin = $this->locatePostgresBin();
        if (! $bin) {
            $this->error('Could not find pg_dump/pg_restore. Install PostgreSQL 18+ client tools (a DBngin 18 instance, or `brew install postgresql@18`).');

            return self::FAILURE;
        }
        $this->line("PostgreSQL tools: {$bin['dir']} (pg_dump {$bin['version']})");

        // ── Verify the tunnel to production is reachable ──────────────────
        if (! $this->canConnect($prod['host'], (int) $prod['port'])) {
            $this->error("Cannot reach production at {$prod['host']}:{$prod['port']}. The SSH tunnel is not up.");
            $this->line("  Open the tunnel first (the 'db-tunnel' alias), then retry. For example:");
            $this->line("    ssh -L {$prod['port']}:127.0.0.1:5432 -fN <user>@<production-host>");

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn(sprintf(
            "This WIPES local '%s' (%s:%s) and replaces it with production '%s' (%s:%s).",
            $local['database'], $local['host'], $local['port'],
            $prod['database'], $prod['host'], $prod['port'],
        ));

        if ($this->option('dry-run')) {
            $this->info('[dry run] Would dump (slim), drop+recreate local, restore, clear caches.');
            $this->line('  excluded table data: '.implode(', ', self::EXCLUDE_TABLE_DATA));

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('Continue?')) {
            return self::SUCCESS;
        }

        $dumpPath = storage_path('app/'.$prod['database'].'_prod_'.date('Ymd_His').'.dump');

        // ── 1/3 Dump production (slim) ────────────────────────────────────
        $this->info('1/3 Dumping production (slim)...');
        $dumpArgs = [
            "{$bin['dir']}/pg_dump",
            '-h', $prod['host'], '-p', (string) $prod['port'],
            '-U', $prod['username'], '-d', $prod['database'],
            '-Fc', '--no-owner', '--no-privileges',
        ];
        foreach (self::EXCLUDE_TABLE_DATA as $table) {
            $dumpArgs[] = "--exclude-table-data={$table}";
        }
        $dumpArgs[] = '-f';
        $dumpArgs[] = $dumpPath;

        if (! $this->runProcess($dumpArgs, $this->pgEnv($prod['password']))) {
            $this->error('Dump failed. Check production credentials (DB_PROD_PASSWORD / ~/.pgpass) and the tunnel.');

            return self::FAILURE;
        }
        $this->line('    dump size: '.$this->humanBytes((int) filesize($dumpPath)));

        // ── 2/3 Recreate the local database ───────────────────────────────
        $this->info('2/3 Recreating local database...');
        $this->runProcess([
            "{$bin['dir']}/dropdb", '-h', $local['host'], '-p', (string) $local['port'],
            '-U', $local['username'], '--force', '--if-exists', $local['database'],
        ], $this->pgEnv($local['password']), allowFailure: true);

        if (! $this->runProcess([
            "{$bin['dir']}/createdb", '-h', $local['host'], '-p', (string) $local['port'],
            '-U', $local['username'], $local['database'],
        ], $this->pgEnv($local['password']))) {
            $this->error('Could not create the local database. Check the local PostgreSQL server / DB_USERNAME.');

            return self::FAILURE;
        }

        // ── 3/3 Restore (parallel; ignore harmless owner/role warnings) ───
        $this->info('3/3 Restoring into local...');
        $this->runProcess([
            "{$bin['dir']}/pg_restore", '-h', $local['host'], '-p', (string) $local['port'],
            '-U', $local['username'], '-d', $local['database'],
            '--no-owner', '--no-privileges', '--no-comments', '-j', '4', $dumpPath,
        ], $this->pgEnv($local['password']), allowFailure: true);

        // ── Post-restore housekeeping ─────────────────────────────────────
        DB::purge('pgsql');
        DB::reconnect('pgsql');
        $this->callSilent('config:clear');
        if (! file_exists(public_path('storage'))) {
            $this->callSilent('storage:link');
        }

        $this->applyFreshPassword();
        $this->pruneLocalMediaIfRequested();

        if (! $this->option('keep-dump')) {
            @unlink($dumpPath);
        } else {
            $this->line("    dump kept at: {$dumpPath}");
        }

        $this->newLine();
        $this->info('✔ Production database pulled into local.');
        $this->line('  Media streams from the production CDN (disk=r2). Nothing was downloaded.');
        $this->line('  If encrypted fields (payment gateways, 2FA) error, copy production APP_KEY into local .env.');

        return self::SUCCESS;
    }

    /**
     * Pick the newest pg_dump on this machine (DBngin / Homebrew / Postgres.app).
     * Newest wins because it must be ≥ the production server's major version.
     *
     * @return array{dir:string,major:int,version:string}|null
     */
    private function locatePostgresBin(): ?array
    {
        $dirs = array_merge(
            glob('/Users/Shared/DBngin/postgresql/*/bin') ?: [],
            glob('/opt/homebrew/opt/postgresql@*/bin') ?: [],
            glob('/usr/local/opt/postgresql@*/bin') ?: [],
            glob('/Applications/Postgres.app/Contents/Versions/*/bin') ?: [],
            ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'],
        );

        $best = null;
        foreach ($dirs as $dir) {
            $pgDump = "{$dir}/pg_dump";
            if (! is_executable($pgDump)) {
                continue;
            }
            // A candidate may be a broken install (e.g. Homebrew with a missing
            // libpq dylib) that aborts via signal; skip it instead of failing.
            try {
                $result = Process::timeout(15)->run([$pgDump, '--version']);
            } catch (\Throwable) {
                continue;
            }
            if (! $result->successful() || ! preg_match('/(\d+)(?:\.\d+)*/', $result->output(), $m)) {
                continue;
            }
            $major = (int) $m[1];
            if (! $best || $major > $best['major']) {
                $best = [
                    'dir' => $dir,
                    'major' => $major,
                    'version' => trim(str_ireplace('pg_dump (PostgreSQL)', '', $result->output())),
                ];
            }
        }

        return $best;
    }

    private function canConnect(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 3);
        if ($connection) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string,string>  $env
     */
    private function runProcess(array $command, array $env = [], bool $allowFailure = false): bool
    {
        $result = Process::forever()->env($env)->run($command);

        if (! $result->successful() && ! $allowFailure) {
            $error = trim($result->errorOutput() ?: $result->output());
            if ($error !== '') {
                $this->line($error);
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string,string>
     */
    private function pgEnv(?string $password): array
    {
        return $password !== null && $password !== '' ? ['PGPASSWORD' => $password] : [];
    }

    private function applyFreshPassword(): void
    {
        $password = $this->option('fresh-password');
        if ($password === null) {
            return;
        }

        $emails = $this->option('fresh-email');
        if (empty($emails)) {
            $this->warn('  --fresh-password given without --fresh-email; skipping password reset.');

            return;
        }

        foreach ($emails as $email) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $this->warn("  user not found: {$email}");

                continue;
            }
            $user->password = $password;
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
            $user->save();
            $this->line("    password set: {$email}");
        }
    }

    private function pruneLocalMediaIfRequested(): void
    {
        if (! $this->option('prune-media')) {
            return;
        }

        $root = storage_path('app/public');
        $freed = 0;
        $count = 0;
        foreach (File::directories($root) as $dir) {
            foreach (File::allFiles($dir) as $file) {
                $freed += $file->getSize();
            }
            File::deleteDirectory($dir);
            $count++;
        }
        $this->line("    pruned {$count} local media folders (".$this->humanBytes($freed).' freed)');
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }
}
