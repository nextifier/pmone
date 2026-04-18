<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_event_allotments', function (Blueprint $table) {
            $table->dropUnique('hotel_event_allotments_unique');
            $table->dropIndex(['event_id', 'is_active']);
            $table->dropConstrainedForeignId('event_id');

            $table->unique(
                ['hotel_id', 'room_type_id', 'start_date', 'end_date'],
                'hotel_allotments_unique'
            );
            $table->index(['hotel_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_event_allotments', function (Blueprint $table) {
            $table->dropUnique('hotel_allotments_unique');
            $table->dropIndex(['hotel_id', 'is_active']);

            $table->foreignId('event_id')
                ->after('ulid')
                ->constrained()
                ->cascadeOnDelete();
            $table->unique(
                ['event_id', 'hotel_id', 'room_type_id', 'start_date', 'end_date'],
                'hotel_event_allotments_unique'
            );
            $table->index(['event_id', 'is_active']);
        });
    }
};
