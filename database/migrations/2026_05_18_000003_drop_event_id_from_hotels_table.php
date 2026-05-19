<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Postgres unique indexes apply to ALL rows including soft-deleted.
        // Rename trashed hotel slugs to free up the canonical slug for the global unique constraint.
        $trashed = DB::table('hotels')->whereNotNull('deleted_at')->get(['id', 'slug']);
        foreach ($trashed as $row) {
            DB::table('hotels')
                ->where('id', $row->id)
                ->update(['slug' => $row->slug.'--deleted-'.$row->id]);
        }

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'slug']);
            $table->dropIndex(['event_id']);
            $table->dropConstrainedForeignId('event_id');
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->foreignId('event_id')->nullable()->after('ulid')->constrained()->cascadeOnDelete();
            $table->unique(['event_id', 'slug']);
            $table->index('event_id');
        });
    }
};
