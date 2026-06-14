<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds the rotating hero CTA strip that used to live hardcoded in each
 * pmone-events app's content.js (`components.hero.announcements`) into the
 * project-level ProjectBanner feature (placement `hero-announcement`, type
 * `text`). Each item is a short text + link; the text is stored on both `title`
 * and `cta_label` so PublicBannerResource's text branch surfaces it as
 * `subHeadline` and `cta.{label,link}`. Mirrors VisitorCtaBannersSeeder.
 *
 * Idempotent: a project is skipped when it already has any `hero-announcement`
 * banner. Run manually: php artisan db:seed --class=HeroAnnouncementsSeeder
 */
class HeroAnnouncementsSeeder extends Seeder
{
    private const EXHIBITOR = 'Space is still available for exhibitors';

    private const VISITOR = 'Visitor Registration Is Now Open!';

    /**
     * Reusable announcement item definitions.
     *
     * @return array<string, array<string, string>>
     */
    private function defs(): array
    {
        return [
            'exhibitor' => ['text' => self::EXHIBITOR, 'link' => '/book-space'],
            'visitor' => ['text' => self::VISITOR, 'link' => '/ticket'],
        ];
    }

    /**
     * Per-project announcement lists (order matters). Project username maps from
     * the pmone-events app folder: cbe serves cafeexpo/icf/cokelatexpo, ioe is
     * outingexpo. Apps whose exhibitor item was commented out (megabuild,
     * keramika) only carry the visitor item.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function projects(): array
    {
        $defs = $this->defs();

        return [
            'megabuild' => [$defs['visitor']],
            'cbe' => [$defs['exhibitor'], $defs['visitor']],
            'flei' => [$defs['exhibitor'], $defs['visitor']],
            'morefood' => [$defs['exhibitor'], $defs['visitor']],
            'icc' => [$defs['exhibitor']],
            'inacon' => [$defs['exhibitor']],
            'keramika' => [$defs['visitor']],
            'ioe' => [$defs['exhibitor']],
            'renex' => [$defs['exhibitor']],
        ];
    }

    public function run(): void
    {
        foreach ($this->projects() as $username => $items) {
            $project = Project::where('username', $username)->first();

            if (! $project) {
                $this->command->warn("Project '{$username}' not found, skipping.");

                continue;
            }

            if ($project->banners()->where('placement', 'hero-announcement')->exists()) {
                $this->command->info("'{$username}': already has hero-announcement banners, skipping.");

                continue;
            }

            $order = 0;
            foreach ($items as $item) {
                $project->banners()->create([
                    'placement' => 'hero-announcement',
                    'type' => 'text',
                    'title' => $item['text'],
                    'cta_label' => $item['text'],
                    'link' => $item['link'],
                    'is_active' => true,
                    'sort_order' => $order++,
                ]);
            }

            $this->command->info("'{$username}': ".count($items).' hero-announcement banner(s) created.');
        }
    }
}
