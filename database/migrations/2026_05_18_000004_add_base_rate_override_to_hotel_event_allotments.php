<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_event_allotments', function (Blueprint $table) {
            $table->decimal('base_rate_override', 14, 2)->nullable()->after('surcharge_amount');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_event_allotments', function (Blueprint $table) {
            $table->dropColumn('base_rate_override');
        });
    }
};
