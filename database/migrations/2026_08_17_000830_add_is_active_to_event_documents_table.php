<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_documents', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('blocks_next_step');
            // Mirrors event_products: every exhibitor-facing read is
            // "this event's visible documents, in order".
            $table->index(['event_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('event_documents', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
