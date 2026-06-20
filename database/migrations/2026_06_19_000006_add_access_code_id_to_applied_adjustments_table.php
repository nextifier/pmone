<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applied_adjustments', function (Blueprint $table) {
            $table->foreignId('access_code_id')->nullable()->after('promo_code_id')->constrained()->nullOnDelete();
            $table->index('access_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('applied_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_code_id');
        });
    }
};
