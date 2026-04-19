<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['event_id', 'status'], 'reservations_event_status_idx');
            $table->index(['hotel_id', 'status'], 'reservations_hotel_status_idx');
            $table->index(['status', 'payment_expires_at'], 'reservations_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_event_status_idx');
            $table->dropIndex('reservations_hotel_status_idx');
            $table->dropIndex('reservations_status_expires_idx');
        });
    }
};
