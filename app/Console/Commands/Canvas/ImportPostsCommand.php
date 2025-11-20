<?php

namespace App\Console\Commands\Canvas;

use App\Models\User;
use App\Services\Canvas\CanvasImporter;
use App\Services\Canvas\CanvasPostImporter;
use Illuminate\Console\Command;

class ImportPostsCommand extends Command
{
    protected $signature = 'canvas:import-posts
                            {--limit= : Number of posts to import (leave empty for all)}
                            {--dry-run : Run without saving to database}';

    protected $description = 'Import posts from Canvas export JSON files';

    public function handle(): int
    {
        $this->info('Starting Canvas posts import...');

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        try {
            // Ensure Balboa Estate user exists
            if (! $dryRun) {
                $this->ensureBalboaUserExists();
            }

            // Initialize importer
            $importer = new CanvasImporter;

            // Show summary
            $this->info('Canvas data loaded:');
            $this->info('- Posts: '.count($importer->getPosts()));
            $this->info('- Tags: '.count($importer->getTags()));
            $this->info('- Topics: '.count($importer->getTopics()));
            $this->newLine();

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
            $posts = $importer->getPosts();
            $totalPosts = $limit ?? count($posts);
            $progressBar = $this->output->createProgressBar($totalPosts);
            $progressBar->start();

            // Import posts with progress tracking (pass progress bar to importer)
            $postImporter = new CanvasPostImporter($importer, $dryRun, $limit, $progressBar);
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
            } else {
                $this->newLine();
                $this->info('All posts have been imported successfully!');
                $this->info('All posts are assigned to: Balboa Estate (hello@balboaestate.id)');
                $this->info('Tags and topics have been merged into the tags system.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to import posts: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    protected function ensureBalboaUserExists(): void
    {
        $balboaUser = User::firstOrCreate(
            ['email' => 'hello@balboaestate.id'],
            [
                'name' => 'Balboa Estate',
                'status' => 'active',
                'visibility' => 'public',
            ]
        );

        if ($balboaUser->wasRecentlyCreated) {
            $this->info('Created Balboa Estate user (hello@balboaestate.id)');

            // Assign user role
            if (method_exists($balboaUser, 'assignRole')) {
                $balboaUser->assignRole('user');
            }
        } else {
            $this->info('Balboa Estate user already exists');
        }
    }
}
