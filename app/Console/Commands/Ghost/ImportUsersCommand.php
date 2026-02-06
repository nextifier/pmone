<?php

namespace App\Console\Commands\Ghost;

use App\Services\Ghost\GhostImporter;
use App\Services\Ghost\GhostUserImporter;
use Illuminate\Console\Command;

class ImportUsersCommand extends Command
{
    protected $signature = 'ghost:import-users
                            {--dry-run : Run without saving to database}';

    protected $description = 'Import users from Ghost export JSON';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        $this->info('Starting Ghost users import...');

        try {
            // Initialize importer
            $importer = new GhostImporter;
            $userImporter = new GhostUserImporter($importer);

            // Show users to be imported
            $users = $importer->getData('users');
            $this->info('Found '.count($users).' users in Ghost export');
            $this->newLine();

            // Show table of users
            $this->table(
                ['Email', 'Name', 'Status'],
                collect($users)->map(fn ($user) => [
                    $user['email'],
                    $user['name'],
                    $user['status'],
                ])->toArray()
            );

            $this->newLine();

            if (! $this->confirm('Do you want to proceed with import?', true)) {
                $this->warn('Import cancelled');

                return self::SUCCESS;
            }

            // Import users
            $result = $userImporter->import($dryRun);

            // Show results
            $this->newLine();
            $this->info('Import completed!');
            $this->info('Created: '.$result['created']);
            $this->info('Skipped: '.$result['skipped']);

            if (! empty($result['errors'])) {
                $this->error('Errors: '.count($result['errors']));
                $this->table(
                    ['Email', 'Error'],
                    collect($result['errors'])->map(fn ($error) => [
                        $error['email'],
                        $error['error'],
                    ])->toArray()
                );
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to import users: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
