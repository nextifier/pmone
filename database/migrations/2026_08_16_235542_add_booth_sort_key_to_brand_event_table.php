<?php

use App\Models\BrandEvent;
use App\Support\InputNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_event', function (Blueprint $table) {
            $table->string('booth_sort_key', 60)->nullable()->after('booth_number');
            $table->index(['event_id', 'booth_sort_key']);
        });

        // Backfilled in PHP so the key comes from the one authoritative
        // implementation rather than a SQL transcription of it that can drift.
        // Model events are off: this touches every row, and firing activity log
        // and response-cache listeners thousands of times would be pure waste.
        BrandEvent::withoutEvents(function () {
            BrandEvent::query()
                ->whereNotNull('booth_number')
                ->select(['id', 'booth_number'])
                ->chunkById(500, function ($chunk) {
                    foreach ($chunk as $brandEvent) {
                        BrandEvent::query()->whereKey($brandEvent->id)->update([
                            'booth_sort_key' => InputNormalizer::boothSortKey($brandEvent->booth_number),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        Schema::table('brand_event', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'booth_sort_key']);
            $table->dropColumn('booth_sort_key');
        });
    }
};
