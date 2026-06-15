<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds the cross-promo "Visitor CTA" banners that used to live hardcoded in
 * each pmone-events app's content.js (`components.visitorCta.banners`) into the
 * project-level ProjectBanner feature (placement `visitor-cta`, type
 * `image_text`). subtitle + accentColor are stored in the banner `settings`
 * JSON and surfaced by PublicBannerResource.
 *
 * Idempotent: a project is skipped when it already has any `visitor-cta` banner.
 * Run manually: php artisan db:seed --class=VisitorCtaBannersSeeder
 */
class VisitorCtaBannersSeeder extends Seeder
{
    private const IMG_DIR = __DIR__.'/visitor-cta-banners';

    /**
     * Reusable cross-promo banner definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    private function defs(): array
    {
        return [
            'flei' => [
                'image' => 'flei.jpg',
                'subtitle' => 'Franchise & License Expo Indonesia',
                'title' => 'Your Entrepreneurial Journey Starts Here.',
                'description' => 'Looking to start your own business? Discover hundreds of proven franchise opportunities from top local and international brands. Find your future venture!',
                'accentColor' => ['light' => '#0891b2', 'dark' => '#06b6d4'],
                'cta' => ['label' => 'Explore FLEI', 'link' => 'https://franchise-expo.co.id'],
            ],
            'cbe' => [
                'image' => 'cbe.jpg',
                'subtitle' => "Cafe n' Brasserie Expo",
                'title' => 'For the Love of Coffee & More.',
                'description' => 'Immerse yourself in the world of coffee, tea, and fine foods. The perfect gathering for F&B professionals and aspiring cafe owners to source and connect.',
                'accentColor' => ['light' => '#795548', 'dark' => '#a1887f'],
                'cta' => ['label' => 'Explore CBE', 'link' => 'https://cafebrasserieexpo.com/'],
            ],
            'morefood' => [
                'image' => 'morefood.jpg',
                'subtitle' => 'MoreFood Expo Indonesia',
                'title' => 'Where Every Ingredient Tells a Story.',
                'description' => 'MoreFood Expo is serving up more than just food. Discover fresh ideas, new partners, and a whole menu of ways to grow your business.',
                'accentColor' => ['light' => '#e7000b', 'dark' => '#ff6467'],
                'cta' => ['label' => 'Explore MoreFood', 'link' => 'https://morefoodexpo.com'],
            ],
            'renex' => [
                'image' => 'renex.jpg',
                'subtitle' => 'Renovation Expo',
                'title' => 'The Blueprint for Your Home Renovation.',
                'description' => 'Take control of your renovation. Touch and feel the latest materials, get direct advice from designers, and build your project with total confidence.',
                'accentColor' => ['light' => '#2563eb', 'dark' => '#60a5fa'],
                'cta' => ['label' => 'Explore RENEX', 'link' => 'https://megabuild.co.id'],
            ],
        ];
    }

    /**
     * Per-project banner lists (order matters). Values are reusable defs or
     * inline banner arrays.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function projects(): array
    {
        $defs = $this->defs();

        return [
            'megabuild' => [$defs['flei'], $defs['cbe'], $defs['morefood']],
            'keramika' => [$defs['flei'], $defs['cbe'], $defs['morefood']],
            'cbe' => [$defs['flei'], $defs['morefood']],
            'flei' => [$defs['cbe'], $defs['morefood']],
            'morefood' => [$defs['cbe'], $defs['flei']],
            'renex' => [$defs['cbe'], $defs['flei']],
            'ioe' => [$defs['renex'], $defs['cbe'], $defs['flei']],
            'globalaiexpo' => [
                [
                    'image' => 'globalai-1.jpg',
                    'subtitle' => 'Global AI Expo Conference',
                    'title' => 'Four Tracks. Sixty Sessions. One Roof.',
                    'description' => 'Enterprise AI, robotics, healthcare AI, and policy. Single ticket covers all four conference rooms across three days.',
                    'accentColor' => ['light' => '#2563eb', 'dark' => '#60a5fa'],
                    'cta' => ['label' => 'See speakers', 'link' => '/rundown'],
                ],
                [
                    'image' => 'globalai-2.jpg',
                    'subtitle' => 'Startup Pavilion',
                    'title' => '100+ Startups. USD 100K Prize Pool.',
                    'description' => "Asia's largest applied AI startup floor. Live pitches, investor matching, and a prize pool for breakout teams.",
                    'accentColor' => ['light' => '#9333ea', 'dark' => '#c084fc'],
                    'cta' => ['label' => 'Explore startups', 'link' => '/programs'],
                ],
            ],
            // campx + panorama-events use a bespoke <Cta /> section (not the
            // shared <VisitorCta />). accentColor shape follows their component:
            // campx expects {light,dark}, panorama-events expects a single hex.
            'campx' => [
                [
                    'image' => 'campx-panoramaevents.jpg',
                    'subtitle' => 'Panorama Events',
                    'title' => 'Outing Impian, Tanpa Ribet.',
                    'description' => 'Mau outing tapi pusing ngurusinnya? Serahin aja ke ahlinya! Panorama Events siap atur semua kebutuhan acaramu dari A-Z. Kamu & tim tinggal nikmatin keseruannya.',
                    'accentColor' => ['light' => '#ea580c', 'dark' => '#f97316'],
                    'cta' => ['label' => 'Konsultasi Outing Gratis', 'link' => 'https://panoramaevents.id'],
                ],
                [
                    'image' => 'campx-ioe.jpg',
                    'subtitle' => 'Indonesia Outing Expo 2025',
                    'title' => 'Berburu Promo & Gear Impianmu.',
                    'description' => 'Siap-siap! Pameran outing terbesar se-Indonesia akan hadir di JICC Senayan, 14-16 Nov 2025. Dapatkan diskon gila-gilaan untuk alat camping & paket liburan. Jangan lupa mampir ke booth CampX ya!',
                    'accentColor' => ['light' => '#0891b2', 'dark' => '#06b6d4'],
                    'cta' => ['label' => 'Info Lengkap Expo', 'link' => 'https://indooutingexpo.co.id'],
                ],
            ],
            'pe' => [
                [
                    'image' => 'pe-campx.jpg',
                    'subtitle' => 'CampX',
                    'title' => "Panorama Events' Top Outing Pick!",
                    'description' => "Stuck for outing ideas? Visit CampX, recommended by Panorama Events! With fun activities and stunning, refreshing nature, it's perfect for your team.",
                    'accentColor' => '#22C55E',
                    'cta' => ['label' => 'Explore CampX', 'link' => 'https://campx.id'],
                ],
                [
                    'image' => 'pe-ioe.jpg',
                    'subtitle' => 'Indonesia Outing Expo',
                    'title' => 'Need Fun Travel Ideas & Promos?',
                    'description' => "Find fresh inspiration and awesome travel deals for your team at Indonesia Outing Expo 2025! Don't miss out. (JICC, 14-16 Nov)",
                    'accentColor' => '#38BDF8',
                    'cta' => ['label' => 'Explore IOE 2025', 'link' => 'https://indooutingexpo.co.id'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->projects() as $username => $banners) {
            $project = Project::where('username', $username)->first();

            if (! $project) {
                $this->command->warn("Project '{$username}' not found, skipping.");

                continue;
            }

            if ($project->banners()->where('placement', 'visitor-cta')->exists()) {
                $this->command->info("'{$username}': already has visitor-cta banners, skipping.");

                continue;
            }

            $order = 0;
            foreach ($banners as $data) {
                $banner = $project->banners()->create([
                    'placement' => 'visitor-cta',
                    'type' => 'image_text',
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'cta_label' => $data['cta']['label'],
                    'link' => $data['cta']['link'],
                    'aspect_ratio' => '4:5',
                    'is_active' => true,
                    'sort_order' => $order++,
                    'settings' => [
                        'subtitle' => $data['subtitle'],
                        'accentColor' => $data['accentColor'],
                    ],
                ]);

                $path = self::IMG_DIR.'/'.$data['image'];
                if (is_file($path)) {
                    $banner->addMedia($path)->preservingOriginal()->toMediaCollection('image');
                } else {
                    $this->command->warn("Image '{$data['image']}' missing for '{$username}'.");
                }
            }

            $this->command->info("'{$username}': ".count($banners).' visitor-cta banner(s) created.');
        }
    }
}
