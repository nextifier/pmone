<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SES was retired in favour of Resend. New rows should default to the
     * provider actually in use, and the SES-only "configuration_set" column
     * (Resend has no equivalent) is dropped. Existing rows are left untouched.
     */
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('mailer')->default('resend')->change();
            $table->dropColumn('configuration_set');
        });

        Schema::table('email_suppressions', function (Blueprint $table) {
            $table->string('source')->default('resend')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('mailer')->default('ses-v2')->change();
            $table->string('configuration_set')->nullable();
        });

        Schema::table('email_suppressions', function (Blueprint $table) {
            $table->string('source')->default('ses')->change();
        });
    }
};
