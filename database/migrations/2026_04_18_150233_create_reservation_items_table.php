<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('allotment_id')->nullable()->constrained('hotel_event_allotments')->nullOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('qty')->default(1);
            $table->string('guest_name')->nullable();
            $table->string('guest_identity', 100)->nullable();
            $table->decimal('rate_per_night', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();

            $table->index('reservation_id');
            $table->index(['check_in_date', 'check_out_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_items');
    }
};
