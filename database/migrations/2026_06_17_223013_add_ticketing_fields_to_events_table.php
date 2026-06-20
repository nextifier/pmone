<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('timezone')->default('Asia/Jakarta')->after('end_date');
            $table->boolean('allow_cross_day')->default(false)->after('timezone');
            $table->boolean('tickets_enabled')->default(false)->after('allow_cross_day');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'allow_cross_day', 'tickets_enabled']);
        });
    }
};
