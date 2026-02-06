<?php

namespace App\Console\Commands\Ghost;

use App\Services\Ghost\GhostImporter;
use App\Services\Ghost\GhostTagImporter;
use Illuminate\Console\Command;

class ImportTagsCommand extends Command
{
    protected $signature = 'ghost:import-tags
                            {--dry-run : Run without saving to database}';

    protected $description = 'Import tags from Ghost export JSON';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        $this->info('Starting Ghost tags import...');

        try {
            $importer = new GhostImporter;
            $tagImporter = new GhostTagImporter($importer);

            $result = $tagImporter->import($dryRun);

            $this->info('Tags imported: '.$result['created']);
            $this->info('Tags skipped: '.$result['skipped']);

            if (! empty($result['errors'])) {
                $this->error('Errors: '.count($result['errors']));
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to import tags: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
