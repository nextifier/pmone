<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedTinyInteger('star_rating')->nullable()->after('description');
            $table->string('google_maps_link', 500)->nullable()->after('longitude');
            $table->text('google_maps_embed_src')->nullable()->after('google_maps_link');
            $table->jsonb('facilities')->nullable()->after('google_maps_embed_src');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['star_rating', 'google_maps_link', 'google_maps_embed_src', 'facilities']);
        });
    }
};
