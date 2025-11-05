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
        Schema::create('ga_properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('property_id')->unique();
            $table->string('account_name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('sync_frequency')->default(10)->comment('Sync frequency in minutes');
            $table->integer('rate_limit_per_hour')->default(12)->comment('Max requests per hour');
            $table->timestamps();

            $table->index('is_active');
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ga_properties');
    }
};
