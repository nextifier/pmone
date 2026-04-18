<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_transfers', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_option_id')->constrained('hotel_transfer_options')->restrictOnDelete();
            $table->string('direction', 10);
            $table->date('transfer_date');
            $table->time('transfer_time')->nullable();
            $table->string('pickup_location', 500)->nullable();
            $table->string('dropoff_location', 500)->nullable();
            $table->string('flight_number', 50)->nullable();
            $table->time('flight_time')->nullable();
            $table->unsignedSmallInteger('pax_count')->default(1);
            $table->unsignedSmallInteger('luggage_count')->nullable();
            $table->text('note')->nullable();
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_transfers');
    }
};
