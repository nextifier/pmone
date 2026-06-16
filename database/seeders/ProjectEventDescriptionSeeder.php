<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds curated, multi-language descriptions into projects.bio and
 * events.description (both translatable via Spatie HasTranslations).
 *
 * Content lives in database/seeders/data/descriptions/{projects,events}.json,
 * keyed by username (projects) or "username/slug" (events), each holding a
 * structured {headline, subheadline, points:[{title, detail}]} per locale.
 * The seeder renders that into semantic HTML: headline -> <h2>,
 * subheadline -> <p>, every point -> <h3> + <p>.
 *
 * Idempotent & non-destructive to other data: only bio/description are touched,
 * and they are refreshed (overwritten) from the curated copy on every run, so
 * re-running yields the same state. Missing projects/events are skipped and
 * logged rather than failing.
 *
 * Run manually: php artisan db:seed --class=ProjectEventDescriptionSeeder
 */
class ProjectEventDescriptionSeeder extends Seeder
{
    private const DIR = 'database/seeders/data/descriptions';

    public function run(): void
    {
        $this->seedProjects($this->load('projects'));
        $this->seedEvents($this->load('events'));
    }

    /**
     * @return array<string, array<string, array{headline?: string, subheadline?: string, points?: list<array{title?: string, detail?: string}>}>>
     */
    private function load(string $kind): array
    {
        $path = base_path(self::DIR."/{$kind}.json");

        if (! is_file($path)) {
            $this->command?->warn("Skipped missing description data: {$kind}.json");

            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $data
     */
    private function seedProjects(array $data): void
    {
        $seeded = 0;

        foreach ($data as $username => $byLocale) {
            $project = Project::query()->where('username', $username)->first();

            if (! $project) {
                $this->command?->warn("Project not found, skipped: {$username}");

                continue;
            }

            $project->setTranslations('bio', $this->renderLocales($byLocale));
            $project->save();
            $seeded++;
        }

        $this->command?->info("Seeded descriptions for {$seeded} project(s).");
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $data
     */
    private function seedEvents(array $data): void
    {
        $seeded = 0;

        foreach ($data as $key => $byLocale) {
            [$username, $slug] = array_pad(explode('/', $key, 2), 2, null);

            $project = Project::query()->where('username', $username)->first();

            if (! $project) {
                $this->command?->warn("Project not found for event, skipped: {$key}");

                continue;
            }

            $event = Event::query()
                ->where('project_id', $project->id)
                ->where('slug', $slug)
                ->first();

            if (! $event) {
                $this->command?->warn("Event not found, skipped: {$key}");

                continue;
            }

            $event->setTranslations('description', $this->renderLocales($byLocale));
            $event->save();
            $seeded++;
        }

        $this->command?->info("Seeded descriptions for {$seeded} event(s).");
    }

    /**
     * Render each locale's structured copy into an HTML string.
     *
     * @param  array<string, array<string, mixed>>  $byLocale
     * @return array<string, string>
     */
    private function renderLocales(array $byLocale): array
    {
        $out = [];

        foreach ($byLocale as $locale => $structured) {
            $out[$locale] = $this->renderHtml($structured);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function renderHtml(array $structured): string
    {
        $esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_NOQUOTES, 'UTF-8');

        $html = '<h2>'.$esc($structured['headline'] ?? '').'</h2>';
        $html .= '<p>'.$esc($structured['subheadline'] ?? '').'</p>';

        foreach ($structured['points'] ?? [] as $point) {
            $html .= '<h3>'.$esc($point['title'] ?? '').'</h3>';
            $html .= '<p>'.$esc($point['detail'] ?? '').'</p>';
        }

        return $html;
    }
}
