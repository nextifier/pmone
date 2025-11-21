<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixPostgresSequences extends Command
{
    protected $signature = 'db:fix-sequences
                            {--table= : Specific table to fix (optional)}
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix PostgreSQL sequences after importing data (common issue after SQL dump imports)';

    public function handle(): int
    {
        // Check if we're using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('This command only works with PostgreSQL databases.');

            return self::FAILURE;
        }

        $this->info('Checking PostgreSQL sequences...');
        $this->newLine();

        $specificTable = $this->option('table');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $tables = $specificTable ? [$specificTable] : $this->getAllTables();
        $fixed = 0;
        $checked = 0;
        $errors = 0;

        foreach ($tables as $table) {
            try {
                $result = $this->checkAndFixSequence($table, $dryRun);
                if ($result === true) {
                    $fixed++;
                }
                $checked++;
            } catch (\Exception $e) {
                $this->error("Error processing table '{$table}': {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Tables checked: {$checked}");
        $this->line("  Sequences fixed: {$fixed}");
        if ($errors > 0) {
            $this->line("  Errors: {$errors}");
        }

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply fixes.');
        }

        return self::SUCCESS;
    }

    protected function getAllTables(): array
    {
        $tables = DB::select("
            SELECT tablename
            FROM pg_catalog.pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename
        ");

        return array_map(fn ($table) => $table->tablename, $tables);
    }

    protected function checkAndFixSequence(string $table, bool $dryRun): bool
    {
        // Get the primary key column (usually 'id')
        $columns = Schema::getColumns($table);
        $primaryKey = null;

        foreach ($columns as $column) {
            if ($column['auto_increment'] ?? false) {
                $primaryKey = $column['name'];
                break;
            }
        }

        if (! $primaryKey) {
            // No auto-increment column found, skip
            return false;
        }

        // Get the sequence name
        $sequenceName = "{$table}_{$primaryKey}_seq";

        // Check if sequence exists
        $sequenceExists = DB::select("
            SELECT EXISTS (
                SELECT 1 FROM pg_class
                WHERE relname = ? AND relkind = 'S'
            ) as exists
        ", [$sequenceName]);

        if (! $sequenceExists[0]->exists) {
            // Sequence doesn't exist, skip
            return false;
        }

        // Get current sequence value
        $sequenceValue = DB::select("SELECT last_value FROM {$sequenceName}");
        $currentSequence = $sequenceValue[0]->last_value ?? 0;

        // Get max ID from table
        $maxId = DB::table($table)->max($primaryKey) ?? 0;

        if ($currentSequence < $maxId) {
            $this->warn("⚠️  Table '{$table}': Sequence out of sync");
            $this->line("    Current sequence: {$currentSequence}");
            $this->line("    Max ID in table:  {$maxId}");
            $this->line('    Next ID will be:  '.($maxId + 1));

            if (! $dryRun) {
                // Fix the sequence
                DB::statement("SELECT setval('{$sequenceName}', COALESCE((SELECT MAX({$primaryKey}) FROM {$table}), 1))");
                $this->info("✓  Fixed sequence for '{$table}'");
            } else {
                $this->line("    Would fix: setval('{$sequenceName}', {$maxId})");
            }

            $this->newLine();

            return true;
        }

        return false;
    }
}
