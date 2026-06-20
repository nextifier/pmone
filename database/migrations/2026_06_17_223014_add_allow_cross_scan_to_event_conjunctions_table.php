<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_conjunctions', function (Blueprint $table) {
            $table->boolean('allow_cross_scan')->default(false)->after('conjunction_label');
        });
    }

    public function down(): void
    {
        Schema::table('event_conjunctions', function (Blueprint $table) {
            $table->dropColumn('allow_cross_scan');
        });
    }
};
