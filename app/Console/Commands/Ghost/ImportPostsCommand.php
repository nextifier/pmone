<?php

namespace App\Console\Commands\Ghost;

use App\Services\Ghost\GhostImporter;
use App\Services\Ghost\GhostPostImporter;
use App\Services\Ghost\GhostTagImporter;
use Illuminate\Console\Command;

class ImportPostsCommand extends Command
{
    protected $signature = 'ghost:import-posts
                            {--limit=10 : Number of posts to import (default: 10, use 0 for all)}
                            {--dry-run : Run without saving to database}
                            {--skip-tags : Skip importing tags}';

    protected $description = 'Import posts from Ghost export JSON';

    public function handle(): int
    {
        $this->info('Starting Ghost posts import...');

        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $skipTags = $this->option('skip-tags');

        if ($limit === 0) {
            $limit = null; // Import all
        }

        try {
            // Initialize importer
            $importer = new GhostImporter;

            // Import tags first (if not skipped)
            if (! $skipTags) {
                $this->info('Importing tags...');
                $tagImporter = new GhostTagImporter($importer);
                $tagResult = $tagImporter->import();

                $this->info('Tags imported: '.$tagResult['created']);
                $this->info('Tags skipped: '.$tagResult['skipped']);

                if (! empty($tagResult['errors'])) {
                    $this->warn('Tag errors: '.count($tagResult['errors']));
                }

                $this->newLine();
            }

            // Import posts
            $this->info('Importing posts...');

            if ($limit) {
                $this->info('Limit: '.$limit.' posts');
            } else {
                $this->info('Importing ALL posts');
            }

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No data will be saved');
            }

            $this->newLine();

            if (! $dryRun && ! $this->confirm('Do you want to proceed with import?', true)) {
                $this->warn('Import cancelled');

                return self::SUCCESS;
            }

            // Create progress bar
            $posts = $importer->getData('posts');
            $totalPosts = $limit ?? count($posts);
            $progressBar = $this->output->createProgressBar($totalPosts);
            $progressBar->start();

            // Import posts with progress tracking
            $postImporter = new GhostPostImporter($importer, $dryRun, $limit);
            $result = $postImporter->import();

            $progressBar->finish();
            $this->newLine(2);

            // Show results
            $this->info('Import completed!');
            $this->info('Created: '.$result['created']);
            $this->info('Skipped: '.$result['skipped']);

            if (! empty($result['errors'])) {
                $this->error('Errors: '.count($result['errors']));
                $this->newLine();

                foreach ($result['errors'] as $error) {
                    $this->error('Post: '.$error['title']);
                    $this->error('Error: '.$error['error']);
                    $this->newLine();
                }
            }

            if ($dryRun) {
                $this->warn('This was a DRY RUN. No data was saved.');
                $this->info('Run without --dry-run to actually import the posts.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to import posts: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
