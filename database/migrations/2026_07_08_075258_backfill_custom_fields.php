<?php

use App\Services\CustomFieldMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Copies legacy field definitions (form_fields, event_custom_fields,
     * project_custom_fields, event_documents) into custom_fields and remaps
     * stored answers. Idempotent; production can re-run it after deploy via
     * `php artisan custom-fields:backfill` to catch rows written by old code
     * during the deploy window. Legacy tables are kept frozen for rollback.
     */
    public function up(): void
    {
        app(CustomFieldMigrator::class)->run();
    }

    public function down(): void
    {
        // Backfill only: reversing would delete migrated definitions and
        // values; rollback path is dropping the new tables via the earlier
        // create migrations.
    }
};
