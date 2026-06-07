<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Faq;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds FAQ items for every event website, migrated from the hardcoded
 * `faq.items` i18n data in the pmone-events monorepo.
 *
 * Data source: `database/seeders/data/faqs.json` (committed to git), one entry
 * per event with the full 5-locale question/answer. Interpolation tokens were
 * renamed to PM One's `{{token}}` syntax and are resolved at request time from
 * the event/project context (see App\Support\FaqTemplate), so changing an
 * event's date/location updates every FAQ automatically.
 *
 * Idempotent: skips items that already exist (matched by event + English
 * question). Attaches to each project's active event, falling back to the most
 * recent one. Projects/events that do not exist locally are skipped.
 *
 * Run with: php artisan db:seed --class=FaqsSeeder
 */
class FaqsSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/faqs.json');

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
                $questionEn = $item['question']['en'] ?? null;

                if (! $questionEn) {
                    continue;
                }

                $exists = Faq::withTrashed()
                    ->where('event_id', $event->id)
                    ->where('question->en', $questionEn)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $faq = Faq::create([
                    'event_id' => $event->id,
                    'question' => $this->cleanTranslations($item['question'] ?? []),
                    'answer' => $this->cleanTranslations($item['answer'] ?? []),
                    'is_active' => true,
                ]);

                $order = (int) ($item['order'] ?? 0);
                if ($order > 0 && $faq->order_column !== $order) {
                    $faq->order_column = $order;
                    $faq->saveQuietly();
                }

                $created++;
            }

            $this->command?->info("Seeded {$created} FAQ(s) for {$username} ({$event->slug}).");
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
