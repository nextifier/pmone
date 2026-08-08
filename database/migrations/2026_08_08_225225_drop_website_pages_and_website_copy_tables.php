<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the last two tables of the dashboard-managed website config.
 *
 * `website_pages` held per-project overrides for the legal page bodies;
 * `website_copy` held per-project, per-locale <title>/<meta description>
 * overrides. Both were read by the event websites until Aug 2026, when that
 * whole layer moved back into the pmone-events repo so the sites could be
 * prerendered. The models, controllers, routes, admin pages and public payload
 * keys are removed in the same change as this migration — nothing reads or
 * writes either table any more.
 *
 * Safe by measurement, not by assumption: production held 2 rows in
 * `website_pages`, both with a body of `<p></p>` (empty by the app's own
 * hasVisibleText()), and 0 rows in `website_copy` — nobody ever saved one.
 *
 * `down()` recreates both schemas exactly, so `migrate:rollback` paired with a
 * code revert restores a working state rather than leaving the old code
 * querying a missing table. The rows are not restored, because there were none
 * worth restoring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('website_copy');
        Schema::dropIfExists('website_pages');
    }

    public function down(): void
    {
        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('body')->nullable();
            $table->date('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });

        Schema::create('website_copy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });
    }
};
