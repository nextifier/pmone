<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectBanner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Seeds hero banners for every event project, imported from the event websites'
 * original `composables/content.js` + i18n data.
 *
 * Data lives in `database/seeders/banners/banners.json` and images in
 * `database/seeders/banner-images/{username}/`, both committed to git so
 * production seeding needs no external assets at run time
 * (mirrors JakartaHotelsPhotosSeeder).
 *
 * Idempotent: banners matched via firstOrCreate on
 * (project_id, placement, type, title); images attached only when the banner
 * has none yet. Safe to re-run.
 *
 * Run with: php artisan db:seed --class=ProjectBannersSeeder
 */
class ProjectBannersSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/banners/banners.json');
        if (! is_file($jsonPath)) {
            $this->command?->error("Banner data not found: {$jsonPath}");

            return;
        }

        $imagesRoot = database_path('seeders/banner-images');
        $dataset = json_decode(file_get_contents($jsonPath), true) ?? [];

        foreach ($dataset as $entry) {
            $project = Project::where('username', $entry['username'])->first();
            if (! $project) {
                $this->command?->warn("Project not found: {$entry['username']}, skipping.");

                continue;
            }

            foreach ($entry['banners'] as $payload) {
                $banner = ProjectBanner::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'placement' => 'hero',
                        'type' => $payload['type'],
                        'title' => $payload['title'],
                    ],
                    [
                        'description' => $payload['description'] ?? null,
                        'cta_label' => $payload['cta_label'] ?? null,
                        'link' => $payload['link'] ?? null,
                        'aspect_ratio' => $payload['aspect_ratio'] ?? null,
                        'is_active' => true,
                        'sort_order' => $payload['sort_order'] ?? 0,
                        'start_time' => ! empty($payload['start_time']) ? Carbon::parse($payload['start_time']) : null,
                        'end_time' => ! empty($payload['end_time']) ? Carbon::parse($payload['end_time']) : null,
                    ],
                );

                if (! empty($payload['image']) && $banner->getMedia('image')->isEmpty()) {
                    $this->attachImage($banner, "{$imagesRoot}/{$entry['username']}/{$payload['image']}");
                }
            }

            $this->command?->info("Seeded banners for {$project->username}: {$project->banners()->count()} total");
        }

        ResponseCache::clear(['banners']);
    }

    private function attachImage(ProjectBanner $banner, string $path): void
    {
        if (! is_file($path)) {
            $this->command?->warn("  ! Banner image not found: {$path}");

            return;
        }

        $customProperties = [];
        $imageInfo = @getimagesize($path);
        if ($imageInfo !== false) {
            $customProperties['width'] = $imageInfo[0];
            $customProperties['height'] = $imageInfo[1];
        }

        try {
            $banner->addMedia($path)
                ->preservingOriginal()
                ->withCustomProperties($customProperties)
                ->toMediaCollection('image');
        } catch (\Throwable $e) {
            $this->command?->warn('  ! Failed to attach '.basename($path).': '.$e->getMessage());
        }
    }
}
