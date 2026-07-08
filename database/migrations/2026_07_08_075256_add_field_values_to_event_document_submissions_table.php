<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_document_submissions', function (Blueprint $table) {
            $table->jsonb('field_values')->nullable()->after('text_value');
        });
    }

    public function down(): void
    {
        Schema::table('event_document_submissions', function (Blueprint $table) {
            $table->dropColumn('field_values');
        });
    }
};
