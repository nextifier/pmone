<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert projects.bio and events.description from plain text to a
     * translatable JSON column. Existing plain HTML is wrapped as the English
     * locale: "<html>" becomes {"en": "<html>"}. NULL stays NULL.
     *
     * PostgreSQL has no implicit text->json cast, so production uses an
     * ALTER ... USING with json_build_object to transform data in place
     * (non-destructive, reversible). On other drivers (SQLite in tests) the
     * tables are migrated fresh with no data, so a plain column change is enough.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE projects
                ALTER COLUMN bio TYPE json
                USING CASE WHEN bio IS NULL THEN NULL ELSE json_build_object('en', bio) END
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE events
                ALTER COLUMN description TYPE json
                USING CASE WHEN description IS NULL THEN NULL ELSE json_build_object('en', description) END
            SQL);

            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->json('bio')->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->json('description')->nullable()->change();
        });
    }

    /**
     * Reverse: collapse the JSON object back to the English string in a text column.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE projects
                ALTER COLUMN bio TYPE text
                USING CASE WHEN bio IS NULL THEN NULL ELSE bio->>'en' END
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE events
                ALTER COLUMN description TYPE text
                USING CASE WHEN description IS NULL THEN NULL ELSE description->>'en' END
            SQL);

            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->text('bio')->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }
};
