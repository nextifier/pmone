<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $events = DB::table('events')
            ->whereNotNull('onsite_penalty_rate')
            ->where('onsite_penalty_rate', '>', 0)
            ->get(['id', 'slug', 'title', 'onsite_penalty_rate']);

        foreach ($events as $event) {
            $slug = "event-onsite-penalty-{$event->slug}";

            $exists = DB::table('promotion_rules')->where('slug', $slug)->exists();
            if ($exists) {
                continue;
            }

            DB::table('promotion_rules')->insert([
                'ulid' => (string) Str::ulid(),
                'name' => "{$event->title} - On-site Penalty",
                'slug' => $slug,
                'description' => 'Auto-generated from Event.onsite_penalty_rate for backward compatibility.',
                'kind' => 'penalty',
                'value_type' => 'percentage',
                'value' => $event->onsite_penalty_rate,
                'max_discount_amount' => null,
                'min_purchase_amount' => null,
                'applies_before_tax' => true,
                'stacking_mode' => 'combinable_with_all',
                'priority' => 50,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
                'target_types' => json_encode(['Order']),
                'applicability' => null,
                'trigger_type' => 'event_period',
                'trigger_config' => json_encode(['phase' => 'onsite']),
                'revert_usage_on_cancel' => true,
                'is_system_manual' => false,
                'event_id' => $event->id,
                'project_id' => null,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('promotion_rules')
            ->where('slug', 'like', 'event-onsite-penalty-%')
            ->where('trigger_type', 'event_period')
            ->delete();
    }
};
