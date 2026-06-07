<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds event gallery photos (Spatie media collection `gallery`), migrated from
 * the hardcoded per-app galleries in the pmone-events monorepo.
 *
 * Photos are stored (as webp) in `database/seeders/gallery-photos/{username}/`
 * committed to git, so production seeding works from the repo (no external CDN
 * or the pmone-events repo required at run time) — same approach as hotel-photos.
 *
 * Attaches to each project's most recent PAST event (start_date <= now; the last
 * completed edition the photos came from), falling back to the oldest event.
 * Idempotent: skips events that already have gallery media. Sets width/height
 * custom properties so the public site can render natural aspect ratios.
 *
 * Run with: php artisan db:seed --class=EventGalleryPhotosSeeder
 */
class EventGalleryPhotosSeeder extends Seeder
{
    private string $photosRoot;

    public function run(): void
    {
        $this->photosRoot = database_path('seeders/gallery-photos');

        if (! is_dir($this->photosRoot)) {
            $this->command?->error("Photos folder not found: {$this->photosRoot}");

            return;
        }

        foreach (scandir($this->photosRoot) as $username) {
            $dir = "{$this->photosRoot}/{$username}";

            if ($username === '.' || $username === '..' || ! is_dir($dir)) {
                continue;
            }

            $event = $this->resolveEvent($username);

            if (! $event) {
                $this->command?->warn("Skipping '{$username}': no project/event found.");

                continue;
            }

            if ($event->getMedia('gallery')->isNotEmpty()) {
                $this->command?->info("Skipping {$username} ({$event->slug}): already has gallery.");

                continue;
            }

            $attached = 0;
            foreach ($this->imageFiles($dir) as $file) {
                $dimensions = @getimagesize($file);

                $media = $event->addMedia($file)
                    ->preservingOriginal()
                    ->toMediaCollection('gallery');

                if ($dimensions) {
                    $media->setCustomProperty('width', $dimensions[0])
                        ->setCustomProperty('height', $dimensions[1])
                        ->save();
                }

                $attached++;
            }

            $this->command?->info("Seeded {$attached} gallery photo(s) for {$username} ({$event->slug}).");
        }
    }

    private function resolveEvent(string $username): ?Event
    {
        $project = Project::where('username', $username)->first();

        if (! $project) {
            return null;
        }

        return $project->events()->where('start_date', '<=', now())->orderByDesc('start_date')->first()
            ?? $project->events()->orderBy('start_date')->first();
    }

    /**
     * @return array<int, string>
     */
    private function imageFiles(string $dir): array
    {
        $files = array_filter(
            scandir($dir),
            fn (string $f): bool => (bool) preg_match('/\.(jpe?g|png|webp)$/i', $f),
        );

        natsort($files);

        return array_map(fn (string $f): string => "{$dir}/{$f}", array_values($files));
    }
}
