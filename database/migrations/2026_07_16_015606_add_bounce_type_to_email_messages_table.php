<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resend classifies a bounce as "transient" (temporary - a full mailbox or
     * throttling) or "permanent" (a dead address). Only permanent bounces hurt
     * sender reputation, so the dashboard needs the split. The value is only
     * available from the delivery webhook, so historical rows stay null.
     */
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('bounce_type')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('bounce_type');
        });
    }
};
