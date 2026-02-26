<?php

namespace App\Console\Commands;

use App\Jobs\ExtractOpenGraphMetadata;
use App\Models\ShortLink;
use Illuminate\Console\Command;

class ExtractShortLinksMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'short-links:extract-metadata
                            {--force : Force re-extract even if metadata already exists}
                            {--active-only : Only extract metadata for active short links}
                            {--limit= : Limit the number of short links to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract OpenGraph metadata for all existing short links';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting OpenGraph metadata extraction for short links...');
        $this->newLine();

        // Build query
        $query = ShortLink::query();

        // Filter active only if requested
        if ($this->option('active-only')) {
            $query->where('is_active', true);
            $this->info('Filtering: Active short links only');
        }

        // Filter links without metadata unless force is set
        if (! $this->option('force')) {
            $query->whereNull('og_title')
                ->whereNull('og_description')
                ->whereNull('og_image');
            $this->info('Filtering: Short links without OG metadata only');
        } else {
            $this->warn('Force mode: Re-extracting metadata for ALL short links');
        }

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
            $this->info("Limiting to {$limit} short links");
        }

        // Get short links
        $shortLinks = $query->get();
        $total = $shortLinks->count();

        if ($total === 0) {
            $this->warn('No short links found to process.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Found {$total} short link(s) to process.");
        $this->newLine();

        // Confirm before processing
        if (! $this->confirm('Do you want to proceed?', true)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        // Create progress bar
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->setFormat('very_verbose');
        $progressBar->start();

        $dispatched = 0;
        $skipped = 0;

        foreach ($shortLinks as $shortLink) {
            try {
                // Dispatch job to queue
                ExtractOpenGraphMetadata::dispatch($shortLink->id);
                $dispatched++;
            } catch (\Throwable $e) {
                $skipped++;
                $this->error("\nFailed to dispatch job for short link #{$shortLink->id} ({$shortLink->slug}): {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Extraction jobs dispatched successfully!');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Short Links', $total],
                ['Jobs Dispatched', $dispatched],
                ['Skipped (Errors)', $skipped],
            ]
        );

        $this->newLine();
        $this->comment('💡 Jobs have been added to the queue. Make sure your queue worker is running:');
        $this->comment('   php artisan queue:work');

        return self::SUCCESS;
    }
}
