<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->unsignedBigInteger('suspended_by')->nullable()->after('suspension_reason');

            $table->foreign('suspended_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropColumn(['suspended_at', 'suspension_reason', 'suspended_by']);
        });
    }
};
