<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: Column reordering is not supported in PostgreSQL without recreating the table.
     * Column order does not affect functionality - only changing json to jsonb here.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN user_settings TYPE jsonb USING user_settings::jsonb');
            DB::statement('ALTER TABLE users ALTER COLUMN more_details TYPE jsonb USING more_details::jsonb');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('phone');
            $table->text('encrypted_password')->nullable()->after('password');
            $table->jsonb('custom_fields')->nullable()->after('more_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'encrypted_password', 'custom_fields']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN user_settings TYPE json USING user_settings::json');
            DB::statement('ALTER TABLE users ALTER COLUMN more_details TYPE json USING more_details::json');
        }
    }
};
