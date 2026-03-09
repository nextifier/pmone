<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the actual constraint name from PostgreSQL
        $constraint = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'contact_form_submissions'::regclass
            AND contype = 'f'
            AND conname LIKE '%followed_up_by%'
        ");

        Schema::table('contact_form_submissions', function (Blueprint $table) use ($constraint) {
            if ($constraint) {
                $table->dropForeign($constraint->conname);
            }
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
