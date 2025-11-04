<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->unsignedBigInteger('clicker_id')->nullable()->after('clickable_id');
            $table->foreign('clicker_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['clicker_id', 'clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            $table->dropForeign(['clicker_id']);
            $table->dropIndex(['clicker_id', 'clicked_at']);
            $table->dropColumn('clicker_id');
        });
    }
};
