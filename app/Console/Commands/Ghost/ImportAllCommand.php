<?php

namespace App\Console\Commands\Ghost;

use Illuminate\Console\Command;

class ImportAllCommand extends Command
{
    protected $signature = 'ghost:import
                            {--limit= : Number of posts to import (leave empty for all)}
                            {--dry-run : Run without saving to database}
                            {--skip-users : Skip importing users}
                            {--skip-tags : Skip importing tags}
                            {--skip-posts : Skip importing posts}
                            {--skip-images : Skip migrating content images}';

    protected $description = 'Import all Ghost data (users, tags, posts, and images)';

    public function handle(): int
    {
        $this->info('===========================================');
        $this->info('  Ghost Import - Complete Migration');
        $this->info('===========================================');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved');
            $this->newLine();
        }

        // Step 1: Import Users
        if (! $this->option('skip-users')) {
            $this->info('Step 1/4: Importing Ghost users...');
            $exitCode = $this->call('ghost:import-users', array_filter([
                '--dry-run' => $dryRun,
            ]));

            if ($exitCode !== 0) {
                $this->error('Failed to import users');

                return self::FAILURE;
            }

            $this->newLine();
        }

        // Step 2: Import Tags
        if (! $this->option('skip-tags')) {
            $this->info('Step 2/4: Importing Ghost tags...');
            $exitCode = $this->call('ghost:import-tags', array_filter([
                '--dry-run' => $dryRun,
            ]));

            if ($exitCode !== 0) {
                $this->error('Failed to import tags');

                return self::FAILURE;
            }

            $this->newLine();
        }

        // Step 3: Import Posts
        if (! $this->option('skip-posts')) {
            $this->info('Step 3/4: Importing Ghost posts...');
            $exitCode = $this->call('ghost:import-posts', array_filter([
                '--limit' => $limit,
                '--dry-run' => $dryRun,
                '--skip-tags' => true, // Tags already imported in step 2
            ]));

            if ($exitCode !== 0) {
                $this->error('Failed to import posts');

                return self::FAILURE;
            }

            $this->newLine();
        }

        // Step 4: Migrate Content Images
        if (! $this->option('skip-images') && ! $dryRun) {
            $this->info('Step 4/4: Migrating Ghost content images to Media Library...');
            $exitCode = $this->call('ghost:migrate-content-images', array_filter([
                '--limit' => $limit,
                '--dry-run' => $dryRun,
            ]));

            if ($exitCode !== 0) {
                $this->error('Failed to migrate content images');

                return self::FAILURE;
            }

            $this->newLine();
        }

        // Summary
        $this->info('===========================================');
        $this->info('  Ghost Import Completed Successfully!');
        $this->info('===========================================');

        if ($dryRun) {
            $this->warn('This was a DRY RUN. No data was saved.');
        }

        return self::SUCCESS;
    }
}
