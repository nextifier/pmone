<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror the posts translatable conversion on post_autosaves so drafts can
     * round-trip the same locale-keyed payloads the editor now sends.
     */
    private const COLUMNS = ['title', 'excerpt', 'content', 'meta_title', 'meta_description'];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::COLUMNS as $column) {
                DB::statement(<<<SQL
                    ALTER TABLE post_autosaves
                    ALTER COLUMN {$column} TYPE json
                    USING CASE WHEN {$column} IS NULL THEN NULL ELSE json_build_object('id', {$column}) END
                SQL);
            }

            return;
        }

        Schema::table('post_autosaves', function (Blueprint $table) {
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
                    ALTER TABLE post_autosaves
                    ALTER COLUMN {$column} TYPE {$type}
                    USING CASE WHEN {$column} IS NULL THEN NULL ELSE COALESCE({$column}->>'id', {$column}->>'en') END
                SQL);
            }

            return;
        }

        Schema::table('post_autosaves', function (Blueprint $table) {
            $table->string('title')->change();
            $table->text('excerpt')->nullable()->change();
            $table->longText('content')->change();
            $table->string('meta_title')->nullable()->change();
            $table->text('meta_description')->nullable()->change();
        });
    }
};
