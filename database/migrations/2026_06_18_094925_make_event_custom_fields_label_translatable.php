<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make event_custom_fields.label translatable. Existing plain labels are
     * wrapped as the English locale: "Areas of interest" becomes
     * {"en": "Areas of interest"}.
     *
     * Mirrors the projects.bio / events.description conversion: PostgreSQL has no
     * implicit text->json cast, so it uses ALTER ... USING json_build_object;
     * SQLite (tests) migrates fresh with no data, so a plain change is enough.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE event_custom_fields
                ALTER COLUMN label TYPE json
                USING CASE WHEN label IS NULL THEN NULL ELSE json_build_object('en', label) END
            SQL);

            return;
        }

        Schema::table('event_custom_fields', function (Blueprint $table) {
            $table->json('label')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE event_custom_fields
                ALTER COLUMN label TYPE varchar(255)
                USING CASE WHEN label IS NULL THEN NULL ELSE label->>'en' END
            SQL);

            return;
        }

        Schema::table('event_custom_fields', function (Blueprint $table) {
            $table->string('label')->change();
        });
    }
};
