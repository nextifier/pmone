<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Partner;
use App\Models\PartnerCategory;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Attaches global partners to events via partner categories + the
 * `partner_category_partner` pivot (with order_column), migrated from the per-app
 * `partners.js` category structure in the pmone-events monorepo.
 *
 * Data source: `database/seeders/data/partner-event-links.json`, keyed by PM One
 * project username (resolved dataSourceUsername||projectUsername; cafeexpo/icf/
 * cokelatexpo are unioned into `cbe`, outingexpo->`ioe`, iicc->`askindo`,
 * global-ai-expo->`globalaiexpo`). Each event lists ordered categories, each with
 * ordered partner slugs that reference PartnersSeeder.
 *
 * Idempotent: categories matched by (event_id, slug); pivot rows skipped when the
 * partner is already attached. Projects/events missing locally are skipped.
 * Run PartnersSeeder first.
 *
 * Run with: php artisan db:seed --class=PartnerEventLinksSeeder
 */
class PartnerEventLinksSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/partner-event-links.json');

        if (! is_file($path)) {
            $this->command?->error("Data file not found: {$path}");

            return;
        }

        /** @var array<int, array{event: string, categories: array<int, array<string, mixed>>}> $events */
        $events = json_decode(file_get_contents($path), true);

        foreach ($events as $entry) {
            $username = $entry['event'];
            $event = $this->resolveEvent($username);

            if (! $event) {
                $this->command?->warn("Skipping '{$username}': no project/event found.");

                continue;
            }

            $catOrder = 0;
            $attached = 0;

            foreach ($entry['categories'] as $cat) {
                $catName = $cat['name'] ?? null;

                if (! $catName) {
                    continue;
                }

                $catOrder++;

                $category = PartnerCategory::firstOrCreate(
                    ['event_id' => $event->id, 'slug' => Str::slug($catName)],
                    [
                        'name' => $catName,
                        'no_container' => $cat['no_container'] ?? false,
                        'order_column' => $catOrder,
                    ],
                );

                $order = (int) DB::table('partner_category_partner')
                    ->where('partner_category_id', $category->id)
                    ->max('order_column');

                foreach ($cat['partners'] ?? [] as $slug) {
                    $partner = Partner::where('slug', $slug)->first();

                    if (! $partner) {
                        $this->command?->warn("  Partner '{$slug}' not found (run PartnersSeeder first).");

                        continue;
                    }

                    $alreadyAttached = DB::table('partner_category_partner')
                        ->where('partner_category_id', $category->id)
                        ->where('partner_id', $partner->id)
                        ->exists();

                    if ($alreadyAttached) {
                        continue;
                    }

                    $category->partners()->attach($partner->id, ['order_column' => ++$order]);
                    $attached++;
                }
            }

            $this->command?->info("Linked {$attached} partner(s) across ".count($entry['categories']).' category(ies) for '."{$username} ({$event->slug}).");
        }

        ResponseCache::clear(['partners']);
    }

    private function resolveEvent(string $username): ?Event
    {
        $project = Project::where('username', $username)->first();

        if (! $project) {
            return null;
        }

        return $project->events()->where('is_active', true)->first()
            ?? $project->events()->orderByDesc('start_date')->first();
    }
}
