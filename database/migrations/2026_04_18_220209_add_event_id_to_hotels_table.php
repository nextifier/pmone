<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DESTRUCTIVE: truncate hotels & cascading children. Approved by user (existing data is dummy).
        Schema::disableForeignKeyConstraints();
        DB::table('reservation_transfers')->truncate();
        DB::table('reservation_items')->truncate();
        DB::table('reservations')->truncate();
        DB::table('hotel_event_allotments')->truncate();
        DB::table('hotel_transfer_options')->truncate();
        DB::table('room_types')->truncate();
        DB::table('hotels')->truncate();
        Schema::enableForeignKeyConstraints();

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->foreignId('event_id')
                ->after('ulid')
                ->constrained()
                ->cascadeOnDelete();
            $table->unique(['event_id', 'slug']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'slug']);
            $table->dropIndex(['event_id']);
            $table->dropConstrainedForeignId('event_id');
            $table->unique('slug');
        });
    }
};
