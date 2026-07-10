<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts brands.company_address (freeform text) into a JSONB `address` column
 * shaped {street, city, province, country} — mirroring the `contacts.address`
 * column. Existing freeform text is copied into the `street` key; the remaining
 * keys start empty and are filled in later through the admin UI.
 *
 * Existing data is copied into the JSONB column BEFORE the old column is
 * dropped, and the whole operation runs inside a transaction so a failure rolls
 * back completely — no data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        DB::transaction(function () use ($isPgsql) {
            Schema::table('brands', function (Blueprint $table) {
                $table->jsonb('address_new')->nullable();
            });

            if ($isPgsql) {
                DB::statement(<<<'SQL'
                    UPDATE brands
                    SET address_new = jsonb_strip_nulls(jsonb_build_object(
                        'street', NULLIF(company_address, ''),
                        'city', NULL,
                        'province', NULL,
                        'country', NULL
                    ))
                    WHERE NULLIF(company_address, '') IS NOT NULL
                SQL);

                $expected = DB::table('brands')
                    ->whereRaw("NULLIF(company_address, '') IS NOT NULL")
                    ->count();
                $migrated = DB::table('brands')->whereNotNull('address_new')->count();

                if ($expected !== $migrated) {
                    throw new RuntimeException(
                        "Brand address migration mismatch: expected {$expected} rows, migrated {$migrated}. Rolling back."
                    );
                }
            }

            Schema::table('brands', function (Blueprint $table) {
                $table->dropColumn('company_address');
            });

            Schema::table('brands', function (Blueprint $table) {
                $table->renameColumn('address_new', 'address');
            });
        });
    }

    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        DB::transaction(function () use ($isPgsql) {
            Schema::table('brands', function (Blueprint $table) {
                $table->renameColumn('address', 'address_jsonb');
            });

            Schema::table('brands', function (Blueprint $table) {
                $table->text('company_address')->nullable();
            });

            if ($isPgsql) {
                DB::statement(<<<'SQL'
                    UPDATE brands
                    SET company_address = NULLIF(CONCAT_WS(', ',
                        NULLIF(address_jsonb->>'street', ''),
                        NULLIF(address_jsonb->>'city', ''),
                        NULLIF(address_jsonb->>'province', ''),
                        NULLIF(address_jsonb->>'country', '')
                    ), '')
                    WHERE address_jsonb IS NOT NULL
                SQL);
            }

            Schema::table('brands', function (Blueprint $table) {
                $table->dropColumn('address_jsonb');
            });
        });
    }
};
