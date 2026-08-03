<?php

use App\Models\User;
use Database\Seeders\ExampleFormsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\ResponseCache\Facades\ResponseCache;

return new class extends Migration
{
    /**
     * Runs ExampleFormsSeeder as part of deploy migrations, since the
     * production deploy pipeline runs `php artisan migrate` but not
     * `db:seed`. The Field Showcase template gained seven field types
     * (month, month_range, year, year_range, time_range, slider_range,
     * slider_ruler) after the example forms were first seeded, so the
     * public showcase still renders 29 fields instead of 36.
     *
     * The seeder is append-only and idempotent — firstOrCreate on the form,
     * syncMissingFields matched on `type|label`, and backfill limited to the
     * newly appended fields — so this only adds what is missing and leaves
     * existing fields and answers untouched. See ExampleFormsSeeder for the
     * full contract.
     */
    public function up(): void
    {
        // A freshly migrated database (CI, `migrate:fresh`, and every test
        // using RefreshDatabase) has no users and no roles. The seeder opens
        // with `User::role('master')`, which throws RoleDoesNotExist when the
        // role table is empty, and seeding demo forms into every test
        // database would skew unrelated counts. Deploy targets always have
        // users, so keying off that covers both.
        if (! User::query()->exists()) {
            return;
        }

        (new ExampleFormsSeeder)->run();

        // GET /api/public/forms/{slug} is response-cached, so without this the
        // public showcase keeps serving the pre-migration field list until the
        // cached entry expires.
        ResponseCache::clear();
    }

    /**
     * Append-only backfill: the appended fields are template fields the form
     * should already have had, so there is nothing meaningful to reverse.
     */
    public function down(): void
    {
        //
    }
};
