<?php

namespace App\Console\Commands;

use App\Services\CustomFieldMigrator;
use Illuminate\Console\Command;

class BackfillCustomFields extends Command
{
    protected $signature = 'custom-fields:backfill';

    protected $description = 'Re-run the idempotent legacy custom-field backfill (form_fields, event_custom_fields, project_custom_fields, event_documents) into custom_fields';

    public function handle(CustomFieldMigrator $migrator): int
    {
        $migrator->run();

        $this->components->info('Custom-field backfill completed.');

        return self::SUCCESS;
    }
}
