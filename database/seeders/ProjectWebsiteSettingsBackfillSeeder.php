<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Backfills the public website display settings that used to live in each
 * pmone-events app.config.ts `settings` object (blog post-card toggles, ticket
 * page tabs, book-space form fields, and the Terms "last updated" date) into the
 * project-level `settings.website_settings.*` JSON, alongside the existing
 * rundown/brands/hotels blocks.
 *
 * Keyed by the PM One username each website actually sources from: apps that
 * share a data source collapse to one project (cafeexpo/cokelatexpo/icf -> cbe,
 * outingexpo -> ioe), so only the resolved projects are seeded.
 *
 * Idempotent: a project is skipped once it already has a migrated `blog` block
 * (so it never overwrites edits made later in the admin UI). Existing
 * rundown/brands/hotels settings are preserved.
 * Run manually: php artisan db:seed --class=ProjectWebsiteSettingsBackfillSeeder
 */
class ProjectWebsiteSettingsBackfillSeeder extends Seeder
{
    /**
     * @return array{blog: array<string, bool>, ticket_tabs: array<string, bool>, book_space_form: array<string, bool>}
     */
    private function defaults(): array
    {
        return [
            'blog' => [
                'show_post_card_author' => false,
                'show_post_card_excerpt' => false,
            ],
            'ticket_tabs' => [
                'show_tickets' => true,
                'show_guests' => false,
                'show_brands' => true,
                'show_rundown' => true,
                'show_about' => true,
                'show_photos' => true,
            ],
            'book_space_form' => [
                'show_job_title' => false,
                'show_brand_name' => true,
                'show_products' => false,
            ],
        ];
    }

    /**
     * Per-project overrides (only values that differ from defaults) + the Terms
     * last-updated date as ISO. Keyed by resolved PM One username.
     *
     * @return array<string, array<string, mixed>>
     */
    private function projects(): array
    {
        return [
            'megabuild' => [
                'book_space_form' => ['show_job_title' => true, 'show_products' => true],
                'terms_last_update' => '2025-08-21',
            ],
            'cbe' => [ // cafeexpo / cokelatexpo / icf
                'terms_last_update' => '2025-08-21',
            ],
            'campx' => [
                'terms_last_update' => '2025-08-21',
            ],
            'morefood' => [
                'ticket_tabs' => ['show_photos' => false],
                'terms_last_update' => '2025-08-21',
            ],
            'pe' => [ // panorama-events
                'terms_last_update' => '2025-08-21',
            ],
            'renex' => [
                'book_space_form' => ['show_job_title' => true, 'show_products' => true],
                'terms_last_update' => '2025-08-21',
            ],
            'globalaiexpo' => [
                'ticket_tabs' => ['show_photos' => false],
                'book_space_form' => ['show_job_title' => true, 'show_products' => true],
                'terms_last_update' => '2026-04-30',
            ],
            'pm' => [ // panorama-media
                'blog' => ['show_post_card_author' => true],
                'terms_last_update' => '2025-08-21',
            ],
            'icc' => [
                'book_space_form' => ['show_products' => true],
                'terms_last_update' => '2025-08-21',
            ],
            'askindo' => [ // iicc
                'blog' => ['show_post_card_author' => true],
                'terms_last_update' => '2026-01-09',
            ],
            'keramika' => [
                'book_space_form' => ['show_job_title' => true, 'show_products' => true],
                'terms_last_update' => '2025-08-21',
            ],
            'inacon' => [
                'ticket_tabs' => ['show_guests' => true],
                'book_space_form' => ['show_products' => true],
                'terms_last_update' => '2025-08-21',
            ],
            'ioe' => [ // outingexpo
                'terms_last_update' => '2025-08-21',
            ],
            'flei' => [
                'terms_last_update' => '2025-12-30',
            ],
        ];
    }

    public function run(): void
    {
        $defaults = $this->defaults();

        foreach ($this->projects() as $username => $overrides) {
            $project = Project::where('username', $username)->first();

            if (! $project) {
                $this->command->warn("Project '{$username}' not found, skipping.");

                continue;
            }

            $settings = $project->settings ?? [];

            if (data_get($settings, 'website_settings.blog') !== null) {
                $this->command->info("'{$username}': already migrated, skipping.");

                continue;
            }

            $block = [
                'blog' => array_replace($defaults['blog'], $overrides['blog'] ?? []),
                'ticket_tabs' => array_replace($defaults['ticket_tabs'], $overrides['ticket_tabs'] ?? []),
                'book_space_form' => array_replace($defaults['book_space_form'], $overrides['book_space_form'] ?? []),
                'terms' => ['last_update' => $overrides['terms_last_update'] ?? null],
            ];

            foreach ($block as $key => $value) {
                data_set($settings, "website_settings.{$key}", $value);
            }

            $project->settings = $settings;
            $project->save();

            $this->command->info("'{$username}': website display settings backfilled.");
        }
    }
}
