<?php

namespace App\Console\Commands\Canvas;

use Illuminate\Console\Command;

class ImportAllCommand extends Command
{
    protected $signature = 'canvas:import
                            {--limit= : Number of posts to import (leave empty for all)}
                            {--dry-run : Run without saving to database}
                            {--skip-posts : Skip importing posts}
                            {--skip-images : Skip migrating content images}';

    protected $description = 'Import all Canvas data (posts and images)';

    public function handle(): int
    {
        $this->info('===========================================');
        $this->info('  Canvas Import - Complete Migration');
        $this->info('===========================================');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved');
            $this->newLine();
        }

        // Step 1: Import Posts
        if (! $this->option('skip-posts')) {
            $this->info('Step 1/2: Importing Canvas posts...');
            $exitCode = $this->call('canvas:import-posts', array_filter([
                '--limit' => $limit,
                '--dry-run' => $dryRun,
            ]));

            if ($exitCode !== 0) {
                $this->error('Failed to import posts');

                return self::FAILURE;
            }

            $this->newLine();
        }

        // Step 2: Migrate Content Images
        if (! $this->option('skip-images') && ! $dryRun) {
            $this->info('Step 2/2: Migrating Canvas content images to Media Library...');
            $exitCode = $this->call('canvas:migrate-content-images', array_filter([
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
        $this->info('  Canvas Import Completed Successfully!');
        $this->info('===========================================');

        if ($dryRun) {
            $this->warn('This was a DRY RUN. No data was saved.');
        }

        return self::SUCCESS;
    }
}
