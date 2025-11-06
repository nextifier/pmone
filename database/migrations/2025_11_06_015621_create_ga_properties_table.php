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
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');

            // Composite indexes
            $table->index(['is_active', 'last_synced_at']);

            // Single column indexes
            $table->index('is_active');
            $table->index('last_synced_at');
            $table->index('account_name');
            $table->index('deleted_at');
            $table->index('created_by');
            $table->index('updated_by');
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
