<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Backfills event display data that used to live in each pmone-events
 * app.config.ts (location_short + teaser_video_id in the event's custom_fields,
 * poster image, and event conjunctions) so the websites can source it from
 * PM One. The public website URL is read from each project's "Website" link.
 *
 * Idempotent & non-destructive: existing events are only gap-filled (never
 * overwritten); missing active events are created from the config values.
 * Run manually: php artisan db:seed --class=EventBackfillSeeder
 */
class EventBackfillSeeder extends Seeder
{
    private const POSTER_DIR = __DIR__.'/event-posters';

    /**
     * @return array<string, array<string, mixed>>
     */
    private function data(): array
    {
        return [
            'megabuild' => [
                'fill' => ['hall' => 'Hall 5, 6, 7'],
                'custom' => ['location_short' => 'NICE PIK 2'],
                'poster' => 'megabuild.jpg',
                'conjunctions' => ['keramika'],
            ],
            'keramika' => [
                'fill' => ['hall' => 'Hall 5, 6, 7'],
                'custom' => ['location_short' => 'NICE PIK 2'],
                'poster' => 'keramika.jpg',
                'conjunctions' => ['megabuild'],
            ],
            'cbe' => [
                'custom' => ['location_short' => 'JIExpo Kemayoran, Jakarta'],
                'poster' => 'cbe.jpg',
                'conjunctions' => ['flei', 'morefood'],
            ],
            'flei' => [
                'custom' => ['location_short' => 'JIExpo Kemayoran, Jakarta'],
                'poster' => 'flei.jpg',
                'conjunctions' => ['cbe', 'morefood'],
            ],
            'morefood' => [
                'custom' => ['location_short' => 'JIExpo Kemayoran, Jakarta'],
                'poster' => 'morefood.jpg',
                'conjunctions' => ['flei', 'cbe'],
            ],
            'inacon' => [
                'custom' => ['location_short' => 'JICC Senayan'],
                'poster' => 'inacon.jpg',
            ],
            'globalaiexpo' => [
                'custom' => ['location_short' => 'Sentul City, Bogor'],
                'poster' => 'globalaiexpo.jpg',
            ],
            'askindo' => [
                'fill' => [
                    'location' => 'Hotel Tentrem Yogyakarta',
                    'location_link' => 'https://maps.app.goo.gl/mgEFte2gyMYJwu8S9',
                    'hall' => 'Ballroom 3',
                ],
                'custom' => ['location_short' => 'Hotel Tentrem Yogyakarta'],
            ],

            // Projects without an active event yet — create from config values.
            'icf' => [
                'custom' => ['location_short' => 'JIExpo Kemayoran, Jakarta'],
                'poster' => 'icf.jpg',
                'conjunctions' => ['flei', 'morefood'],
                'create' => [
                    'title' => 'Indonesia Coffee Festival (ICF)',
                    'slug' => 'indonesia-coffee-festival-2026',
                    'edition_number' => 3,
                    'start_date' => '2026-05-07 10:00:00',
                    'end_date' => '2026-05-10 21:00:00',
                    'location' => 'JIExpo Kemayoran, Jakarta',
                    'location_link' => 'https://maps.app.goo.gl/8GEz5sDjzW6apig97',
                    'description' => 'Festival kopi terbesar di Indonesia. Jelajahi ragam kopi nusantara, peralatan brewing terbaik, dan peluang bisnis di industri kopi.',
                ],
            ],
            'cei' => [
                'custom' => ['location_short' => 'JIExpo Kemayoran, Jakarta'],
                'poster' => 'cei.jpg',
                'conjunctions' => ['flei', 'morefood'],
                'create' => [
                    'title' => 'Cokelat Expo Indonesia',
                    'slug' => 'cokelat-expo-indonesia-2026',
                    'edition_number' => 2,
                    'start_date' => '2026-05-07 10:00:00',
                    'end_date' => '2026-05-10 21:00:00',
                    'location' => 'JIExpo Kemayoran, Jakarta',
                    'location_link' => 'https://maps.app.goo.gl/8GEz5sDjzW6apig97',
                    'description' => 'Pameran cokelat dan kakao pertama di Indonesia. Temukan produk cokelat premium, bahan baku, dan peluang bisnis di industri cokelat dan kakao.',
                ],
            ],
            'icc' => [
                'custom' => ['location_short' => 'NICE, PIK 2'],
                'poster' => 'icc.jpg',
                'create' => [
                    'title' => 'Indonesia Comic Con 2026',
                    'slug' => 'indonesia-comic-con-2026',
                    'edition_number' => 10,
                    'start_date' => '2026-10-03 10:00:00',
                    'end_date' => '2026-10-04 20:00:00',
                    'location' => 'Nusantara International Convention Exhibition (NICE), PIK 2',
                    'location_link' => 'https://maps.app.goo.gl/V6N3r7vxD1BMn9pi8',
                    'description' => 'Ajang pop culture terbesar di Indonesia. Nikmati pengalaman seru bertemu artis internasional, cosplay, komik, merchandise eksklusif, dan banyak lagi.',
                ],
            ],
            'renex' => [
                'custom' => ['location_short' => 'JICC Senayan', 'teaser_video_id' => '1Tsjh4xvtFw'],
                'poster' => 'renex.jpg',
                'create' => [
                    'title' => 'Renovation Expo by Megabuild Indonesia',
                    'slug' => 'renovation-expo-2025',
                    'start_date' => '2025-11-14 10:00:00',
                    'end_date' => '2025-11-16 21:00:00',
                    'location' => 'Jakarta International Convention Center (JICC) Senayan',
                    'location_link' => 'https://maps.app.goo.gl/iAyUVWEbUqHL1mGx7',
                    'description' => 'Pameran renovasi rumah dan interior by Megabuild Indonesia. Temukan inspirasi dan solusi terbaik untuk proyek renovasi dan desain interior Anda.',
                ],
            ],
            'ioe' => [
                'custom' => ['location_short' => 'NICE, PIK 2'],
                'poster' => 'ioe.jpg',
                'create' => [
                    'title' => 'Indonesia Outing & Incentive Travel Expo 2026',
                    'slug' => 'indonesia-outing-incentive-travel-expo-2026',
                    'start_date' => '2026-10-08 10:00:00',
                    'end_date' => '2026-10-11 20:00:00',
                    'location' => 'Nusantara International Convention Exhibition (NICE), PIK 2',
                    'location_link' => 'https://maps.app.goo.gl/MYU5ZFfNoxUzZdjm8',
                    'description' => 'Platform bisnis untuk corporate engagement dan incentive travel. 100+ brand, konferensi HR, business matching.',
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->data() as $username => $config) {
            $project = Project::where('username', $username)->first();

            if (! $project) {
                $this->command->warn("Project '{$username}' not found, skipping.");

                continue;
            }

            $event = $this->resolveEvent($project, $config);

            if (! $event) {
                $this->command->warn("No active event for '{$username}' and no create payload, skipping event.");

                continue;
            }

            $this->gapFill($event, $config['fill'] ?? []);
            $this->gapFillCustom($event, $config['custom'] ?? []);
            $this->attachPoster($event, $config['poster'] ?? null);
            $this->attachConjunctions($event, $config['conjunctions'] ?? []);

            $this->command->info("Backfilled event for '{$username}' (#{$event->id}).");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveEvent(Project $project, array $config): ?Event
    {
        $event = Event::where('project_id', $project->id)
            ->where('is_active', true)
            ->published()
            ->first();

        if ($event || empty($config['create'])) {
            return $event;
        }

        $payload = $config['create'];
        $payload['start_date'] = Carbon::parse($payload['start_date'], 'Asia/Jakarta');
        $payload['end_date'] = Carbon::parse($payload['end_date'], 'Asia/Jakarta');

        return Event::create(array_merge($payload, [
            'project_id' => $project->id,
            'status' => 'published',
            'visibility' => 'public',
            'is_active' => true,
        ]));
    }

    /**
     * Gap-fill real event columns (only sets columns that are currently empty).
     *
     * @param  array<string, mixed>  $fill
     */
    private function gapFill(Event $event, array $fill): void
    {
        $updates = [];

        foreach ($fill as $column => $value) {
            if (blank($event->{$column})) {
                $updates[$column] = $value;
            }
        }

        if ($updates !== []) {
            $event->update($updates);
        }
    }

    /**
     * Gap-fill keys inside the event's custom_fields JSON (only missing keys).
     *
     * @param  array<string, mixed>  $custom
     */
    private function gapFillCustom(Event $event, array $custom): void
    {
        if ($custom === []) {
            return;
        }

        $current = $event->custom_fields ?? [];
        $changed = false;

        foreach ($custom as $key => $value) {
            if (blank(data_get($current, $key))) {
                $current[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $event->update(['custom_fields' => $current]);
        }
    }

    private function attachPoster(Event $event, ?string $poster): void
    {
        if (! $poster || $event->hasMedia('poster_image')) {
            return;
        }

        $path = self::POSTER_DIR.'/'.$poster;

        if (! is_file($path)) {
            $this->command->warn("Poster file '{$poster}' missing on disk, skipping.");

            return;
        }

        $event->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection('poster_image');
    }

    /**
     * @param  array<int, string>  $usernames
     */
    private function attachConjunctions(Event $event, array $usernames): void
    {
        if ($usernames === [] || $event->conjunctionEvents()->exists()) {
            return;
        }

        $sync = [];
        $order = 1;

        foreach ($usernames as $username) {
            $sibling = Event::whereHas('project', fn ($q) => $q->where('username', $username))
                ->where('is_active', true)
                ->published()
                ->first();

            if ($sibling) {
                $sync[$sibling->id] = ['order_column' => $order++];
            }
        }

        if ($sync !== []) {
            $event->conjunctionEvents()->syncWithoutDetaching($sync);
        }
    }
}
