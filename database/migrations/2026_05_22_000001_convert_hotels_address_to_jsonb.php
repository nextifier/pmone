<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges the flat hotel location columns (address, city, country) into a single
 * JSONB `address` column shaped {street, city, province, country} — mirroring the
 * `contacts.address` column. New location keys (e.g. zip) can be added later
 * without another migration.
 *
 * Existing data is copied into the JSONB column BEFORE the old columns are
 * dropped, and the whole operation runs inside a transaction so a failure rolls
 * back completely — no data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        DB::transaction(function () use ($isPgsql) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->jsonb('address_new')->nullable();
            });

            if ($isPgsql) {
                DB::statement(<<<'SQL'
                    UPDATE hotels
                    SET address_new = jsonb_strip_nulls(jsonb_build_object(
                        'street', NULLIF(address, ''),
                        'city', NULLIF(city, ''),
                        'province', NULL,
                        'country', NULLIF(country, '')
                    ))
                    WHERE COALESCE(NULLIF(address, ''), NULLIF(city, ''), NULLIF(country, '')) IS NOT NULL
                SQL);

                $expected = DB::table('hotels')
                    ->whereRaw("COALESCE(NULLIF(address, ''), NULLIF(city, ''), NULLIF(country, '')) IS NOT NULL")
                    ->count();
                $migrated = DB::table('hotels')->whereNotNull('address_new')->count();

                if ($expected !== $migrated) {
                    throw new RuntimeException(
                        "Hotel address migration mismatch: expected {$expected} rows, migrated {$migrated}. Rolling back."
                    );
                }
            }

            Schema::table('hotels', function (Blueprint $table) {
                $table->dropIndex(['city']);
                $table->dropColumn(['address', 'city', 'country']);
            });

            Schema::table('hotels', function (Blueprint $table) {
                $table->renameColumn('address_new', 'address');
            });
        });
    }

    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        DB::transaction(function () use ($isPgsql) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->renameColumn('address', 'address_jsonb');
            });

            Schema::table('hotels', function (Blueprint $table) {
                $table->string('address', 500)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('country', 100)->nullable()->default('Indonesia');
            });

            if ($isPgsql) {
                DB::statement(<<<'SQL'
                    UPDATE hotels
                    SET address = address_jsonb->>'street',
                        city = address_jsonb->>'city',
                        country = COALESCE(address_jsonb->>'country', 'Indonesia')
                    WHERE address_jsonb IS NOT NULL
                SQL);
            }

            Schema::table('hotels', function (Blueprint $table) {
                $table->dropColumn('address_jsonb');
                $table->index('city');
            });
        });
    }
};
