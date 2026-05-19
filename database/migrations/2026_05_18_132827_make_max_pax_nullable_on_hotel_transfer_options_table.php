<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_transfer_options', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_pax')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_transfer_options', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_pax')->default(2)->change();
        });
    }
};
