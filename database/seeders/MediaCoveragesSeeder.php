<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\MediaCoverage;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds media coverage (press) items for every event website, migrated from the
 * hardcoded `useNewsCoveragesStore().list` data in the pmone-events monorepo.
 *
 * Data source: `database/seeders/data/media-coverages.json` (committed to git),
 * keyed by PM One project username (resolved `dataSourceUsername||projectUsername`,
 * e.g. cafeexpo→cbe, outingexpo→ioe). Each item is `{order, title, url, published_at}` —
 * titles stay in their original language (non-translatable).
 *
 * Idempotent: skips items that already exist (matched by event + url). Attaches
 * to each project's active event, falling back to the most recent one. Projects/
 * events that do not exist locally are skipped.
 *
 * Run with: php artisan db:seed --class=MediaCoveragesSeeder
 */
class MediaCoveragesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/media-coverages.json');

        if (! is_file($path)) {
            $this->command?->error("Data file not found: {$path}");

            return;
        }

        /** @var array<int, array{event: string, items: array<int, array<string, mixed>>}> $events */
        $events = json_decode(file_get_contents($path), true);

        foreach ($events as $entry) {
            $username = $entry['event'];
            $event = $this->resolveEvent($username);

            if (! $event) {
                $this->command?->warn("Skipping '{$username}': no project/event found.");

                continue;
            }

            $created = 0;
            foreach ($entry['items'] as $item) {
                $url = $item['url'] ?? null;
                $title = $item['title'] ?? null;

                if (! $url || ! $title) {
                    continue;
                }

                $exists = MediaCoverage::withTrashed()
                    ->where('event_id', $event->id)
                    ->where('url', $url)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $mediaCoverage = MediaCoverage::create([
                    'event_id' => $event->id,
                    'title' => $title,
                    'url' => $url,
                    'published_at' => ! empty($item['published_at']) ? Carbon::parse($item['published_at']) : null,
                    'is_active' => $item['is_active'] ?? true,
                ]);

                $order = (int) ($item['order'] ?? 0);
                if ($order > 0 && $mediaCoverage->order_column !== $order) {
                    $mediaCoverage->order_column = $order;
                    $mediaCoverage->saveQuietly();
                }

                $created++;
            }

            $this->command?->info("Seeded {$created} media coverage(s) for {$username} ({$event->slug}).");
        }
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
