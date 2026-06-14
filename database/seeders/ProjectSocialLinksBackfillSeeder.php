<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Backfills project social links that existed in the pmone-events app.config.ts
 * but were missing from the PM One project links, so the websites can source
 * their social icons entirely from PM One (single source of truth) without a
 * visual regression after the migration.
 *
 * Idempotent: a link is only created when no link with that label (case-
 * insensitive) already exists for the project.
 * Run manually: php artisan db:seed --class=ProjectSocialLinksBackfillSeeder
 */
class ProjectSocialLinksBackfillSeeder extends Seeder
{
    /**
     * @return array<string, array<int, array{label: string, url: string}>>
     */
    private function data(): array
    {
        return [
            'megabuild' => [
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/megabuildid'],
            ],
            'keramika' => [
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/keramikaindonesia'],
            ],
            'flei' => [
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/flei-franchise-and-license-expo-indonesia'],
            ],
            'renex' => [
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/megabuildid'],
            ],
            // global-ai-expo only had an Email link in PM One; restore its socials
            // (handles are placeholders pending real ones — edit in PM One admin).
            'globalaiexpo' => [
                ['label' => 'Instagram', 'url' => 'https://www.instagram.com/globalaiexpo'],
                ['label' => 'Facebook', 'url' => 'https://www.facebook.com/globalaiexpo'],
                ['label' => 'TikTok', 'url' => 'https://tiktok.com/@globalaiexpo'],
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/global-ai-expo'],
                ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@globalaiexpo'],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->data() as $username => $links) {
            $project = Project::where('username', $username)->first();

            if (! $project) {
                $this->command->warn("Project '{$username}' not found, skipping.");

                continue;
            }

            $existing = $project->links()
                ->get()
                ->map(fn ($link) => strtolower((string) $link->label))
                ->all();

            $order = (int) $project->links()->max('order');
            $created = 0;

            foreach ($links as $link) {
                if (in_array(strtolower($link['label']), $existing, true)) {
                    continue;
                }

                $project->links()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'order' => ++$order,
                    'is_active' => true,
                ]);
                $created++;
            }

            $this->command->info("'{$username}': {$created} social link(s) added.");
        }
    }
}
