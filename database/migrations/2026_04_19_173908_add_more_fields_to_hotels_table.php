<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('star_rating');
            $table->string('website_url', 500)->nullable()->after('contact_phone');
            $table->text('cancellation_policy')->nullable()->after('website_url');
            $table->text('children_policy')->nullable()->after('cancellation_policy');
            $table->string('nearest_airport', 150)->nullable()->after('children_policy');
            $table->unsignedSmallInteger('airport_distance_km')->nullable()->after('nearest_airport');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'website_url',
                'cancellation_policy',
                'children_policy',
                'nearest_airport',
                'airport_distance_km',
            ]);
        });
    }
};
