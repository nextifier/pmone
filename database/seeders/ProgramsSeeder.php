<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds main programs for every event website, migrated from the hardcoded
 * `mainPrograms` data in the pmone-events monorepo.
 *
 * Data source: `database/seeders/data/programs.json` (committed to git), one
 * entry per event with the full 5-locale title/description and the icon for
 * each item. Images for image-variant events are attached by ProgramPhotosSeeder.
 *
 * Idempotent: skips items that already exist (matched by event + English title).
 * Attaches to each project's active event, falling back to the most recent one
 * (mirrors the events frontend `rundown.get.ts` resolution). Projects/events
 * that do not exist locally are skipped with a warning.
 *
 * Run with: php artisan db:seed --class=ProgramsSeeder
 */
class ProgramsSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/programs.json');

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
                $titleEn = $item['title']['en'] ?? null;

                if (! $titleEn) {
                    continue;
                }

                $exists = Program::withTrashed()
                    ->where('event_id', $event->id)
                    ->where('title->en', $titleEn)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $program = Program::create([
                    'event_id' => $event->id,
                    'title' => $this->cleanTranslations($item['title'] ?? []),
                    'description' => $this->cleanTranslations($item['description'] ?? []),
                    'icon' => $item['icon'] ?: null,
                    'is_active' => true,
                ]);

                $order = (int) ($item['order'] ?? 0);
                if ($order > 0 && $program->order_column !== $order) {
                    $program->order_column = $order;
                    $program->saveQuietly();
                }

                $created++;
            }

            $this->command?->info("Seeded {$created} program(s) for {$username} ({$event->slug}).");
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

    /**
     * Drop empty-string locales so HasTranslations stores only meaningful values.
     *
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    private function cleanTranslations(array $values): array
    {
        return array_filter(
            $values,
            fn ($v) => is_string($v) && trim($v) !== ''
        );
    }
}
