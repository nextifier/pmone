<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The scalable check-in manifest (plan 022) serves deltas via a
     * "changes since a timestamp" query over `attendees.updated_at`. Index it
     * so delta sync stays fast on very large (20k-50k attendee) events.
     */
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
        });
    }
};
