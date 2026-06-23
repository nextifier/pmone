<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Speeds up per-user login-history and failed-login-count queries
            // (UserSecurityController::loginHistory / securityOverview).
            $table->index(['log_name', 'event', 'subject_id'], 'activity_log_auth_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_auth_index');
        });
    }
};
