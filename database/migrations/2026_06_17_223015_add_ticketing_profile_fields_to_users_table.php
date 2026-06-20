<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country')->nullable()->after('company_name');
            $table->string('city')->nullable()->after('country');
            $table->string('profession')->nullable()->after('city');
            $table->string('position')->nullable()->after('profession');
            $table->boolean('business_matching_opt_in')->default(false)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country', 'city', 'profession', 'position', 'business_matching_opt_in']);
        });
    }
};
