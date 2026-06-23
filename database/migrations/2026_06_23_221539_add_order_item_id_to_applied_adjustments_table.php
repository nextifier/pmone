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
        Schema::table('applied_adjustments', function (Blueprint $table) {
            $table->foreignId('order_item_id')
                ->nullable()
                ->after('adjustable_id')
                ->constrained('order_items')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applied_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
        });
    }
};
