<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->string('view_type', 50)->nullable()->after('bed_type');
            $table->boolean('smoking_allowed')->default(false)->after('breakfast_included');
            $table->text('cancellation_policy')->nullable()->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['view_type', 'smoking_allowed', 'cancellation_policy']);
        });
    }
};
