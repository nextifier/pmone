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
     * Order matters here so the 16 live sites never lose access:
     * 1. Add `api_key_hash` as nullable first (existing rows are unaffected).
     * 2. Backfill it from the CURRENT plaintext `api_key` of every existing
     *    row (including soft-deleted ones, to avoid stray nulls). The sites
     *    keep sending the exact same raw key they always have; the backend
     *    now hashes the incoming key and matches it against this backfilled
     *    value. No key rotation, no site-side change required.
     * 3. Only after every row has a hash do we make the column non-nullable
     *    and unique.
     */
    public function up(): void
    {
        Schema::table('api_consumers', function (Blueprint $table) {
            $table->string('api_key_hash')->nullable()->after('api_key');
        });

        DB::table('api_consumers')
            ->orderBy('id')
            ->select('id', 'api_key')
            ->chunkById(200, function ($consumers) {
                foreach ($consumers as $consumer) {
                    DB::table('api_consumers')
                        ->where('id', $consumer->id)
                        ->update([
                            'api_key_hash' => hash('sha256', $consumer->api_key),
                        ]);
                }
            });

        Schema::table('api_consumers', function (Blueprint $table) {
            $table->string('api_key_hash')->nullable(false)->change();
            $table->unique('api_key_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_consumers', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
            $table->dropColumn('api_key_hash');
        });
    }
};
