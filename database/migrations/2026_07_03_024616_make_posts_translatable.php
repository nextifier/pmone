<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make posts title/excerpt/content/meta_title/meta_description translatable.
     * Existing content is authored in Indonesian, so plain values are wrapped
     * as the Indonesian locale: "Judul" becomes {"id": "Judul"}.
     *
     * Mirrors the event_custom_fields.label conversion: PostgreSQL has no
     * implicit text->json cast, so it uses ALTER ... USING json_build_object;
     * SQLite (tests) migrates fresh with no data, so a plain change is enough.
     */
    private const COLUMNS = ['title', 'excerpt', 'content', 'meta_title', 'meta_description'];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::COLUMNS as $column) {
                DB::statement(<<<SQL
                    ALTER TABLE posts
                    ALTER COLUMN {$column} TYPE json
                    USING CASE WHEN {$column} IS NULL THEN NULL ELSE json_build_object('id', {$column}) END
                SQL);
            }

            // Staff documentation posts are the exception: they are authored
            // in English, so their values belong in the en slot instead.
            foreach (self::COLUMNS as $column) {
                DB::statement(<<<SQL
                    UPDATE posts
                    SET {$column} = json_build_object('en', {$column}->>'id')
                    WHERE settings->>'docs_audience' IS NOT NULL
                      AND {$column} IS NOT NULL
                SQL);
            }

            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->json('title')->change();
            $table->json('excerpt')->nullable()->change();
            $table->json('content')->change();
            $table->json('meta_title')->nullable()->change();
            $table->json('meta_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $types = [
                'title' => 'varchar(255)',
                'excerpt' => 'text',
                'content' => 'text',
                'meta_title' => 'varchar(255)',
                'meta_description' => 'text',
            ];

            foreach ($types as $column => $type) {
                DB::statement(<<<SQL
                    ALTER TABLE posts
                    ALTER COLUMN {$column} TYPE {$type}
                    USING CASE WHEN {$column} IS NULL THEN NULL ELSE COALESCE({$column}->>'id', {$column}->>'en') END
                SQL);
            }

            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->string('title')->change();
            $table->text('excerpt')->nullable()->change();
            $table->longText('content')->change();
            $table->string('meta_title')->nullable()->change();
            $table->text('meta_description')->nullable()->change();
        });
    }
};
