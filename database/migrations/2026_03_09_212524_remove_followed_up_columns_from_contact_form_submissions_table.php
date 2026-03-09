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
        Schema::table('contact_form_submissions', function (Blueprint $table) {
            $table->dropForeign('contact_form_submissions_followed_up_by_fkey');
            $table->dropColumn(['followed_up_by', 'followed_up_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_form_submissions', function (Blueprint $table) {
            $table->timestamp('followed_up_at')->nullable();
            $table->foreignId('followed_up_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
