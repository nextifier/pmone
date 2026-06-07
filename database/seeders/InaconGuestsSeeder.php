<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seeds INACON guests/speakers, migrated from the hardcoded Pinia store in the
 * pmone-events `apps/inacon` app so the website fetches them from PM One.
 *
 * Profile photos (4:5 PNG) live in `database/seeders/guest-photos/inacon/`,
 * committed to git, so production seeding works from the repo (no pmone-events
 * repo or external CDN required at run time) — same approach as gallery-photos.
 *
 * The con runs across two days, so each guest keeps an `appearance_date`
 * ({date, month}) under `more_details` (the public resource exposes it). The
 * role (Cosplayer, Idol Group, …) is stored as `title`.
 *
 * Idempotent: matches on (event_id, slug) so re-runs sync without duplicating,
 * and admin-added guests (other slugs) are left untouched. Run AFTER
 * InaconEventSeeder (the event must exist first).
 *
 * Run with: php artisan db:seed --class=InaconGuestsSeeder
 */
class InaconGuestsSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::where('username', 'inacon')->first();

        if (! $project) {
            $this->command?->warn('Skipping: inacon project not found.');

            return;
        }

        $event = $project->events()->where('is_active', true)->first()
            ?? $project->events()->orderByDesc('start_date')->first();

        if (! $event) {
            $this->command?->warn('Skipping: inacon has no event yet (run InaconEventSeeder first).');

            return;
        }

        $photosDir = database_path('seeders/guest-photos/inacon');

        $order = 0;
        foreach ($this->guests() as $row) {
            $order++;

            $guest = Guest::updateOrCreate(
                ['event_id' => $event->id, 'slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'title' => $row['title'],
                    'status' => 'active',
                    'visibility' => 'public',
                    'is_featured' => false,
                    'more_details' => [
                        'appearance_date' => $row['appearance_date'],
                        'transparent_background' => $row['transparent_background'],
                    ],
                    'order_column' => $order,
                ]
            );

            if ($guest->order_column !== $order) {
                $guest->order_column = $order;
                $guest->saveQuietly();
            }

            $photo = "{$photosDir}/{$row['slug']}.png";

            if (is_file($photo) && $guest->getMedia('profile_image')->isEmpty()) {
                $guest->addMedia($photo)
                    ->preservingOriginal()
                    ->toMediaCollection('profile_image');
            }

            $guest->links()->delete();

            if (! empty($row['instagram'])) {
                $guest->links()->create([
                    'label' => 'Instagram',
                    'url' => 'https://instagram.com/'.$row['instagram'],
                    'order' => 0,
                    'is_active' => true,
                ]);
            }
        }

        $this->command?->info('Seeded '.count($this->guests())." guest(s) for inacon ({$event->slug}).");
    }

    /**
     * appearance_date is a real date range ({start, end} ISO) within the event;
     * the public resource formats it into the "25-26 Oct" display shape.
     *
     * @return array<int, array{name: string, slug: string, title: string, instagram: ?string, appearance_date: ?array{start: string, end: string}, transparent_background: bool}>
     */
    private function guests(): array
    {
        $bothDays = ['start' => '2025-10-25', 'end' => '2025-10-26'];
        $day1 = ['start' => '2025-10-25', 'end' => '2025-10-25'];
        $day2 = ['start' => '2025-10-26', 'end' => '2025-10-26'];

        return [
            ['name' => 'Azulacan', 'slug' => 'azulacan', 'title' => 'Cosplayer', 'instagram' => 'azulacann', 'appearance_date' => $bothDays, 'transparent_background' => true],
            ['name' => 'Kameaam', 'slug' => 'kameaam', 'title' => 'Cosplayer', 'instagram' => 'kameaam', 'appearance_date' => $bothDays, 'transparent_background' => true],
            ['name' => 'Katto', 'slug' => 'katto', 'title' => 'International Cosplayer', 'instagram' => 'katto_cosplay', 'appearance_date' => $bothDays, 'transparent_background' => true],
            ['name' => 'Kohi Sekai', 'slug' => 'kohi-sekai', 'title' => 'Idol Group', 'instagram' => 'kohisekai', 'appearance_date' => $day1, 'transparent_background' => true],
            ['name' => 'UPGIRLS', 'slug' => 'upgirls', 'title' => 'Idol Group', 'instagram' => 'theupgirls', 'appearance_date' => $day2, 'transparent_background' => true],
            ['name' => 'Nextanative', 'slug' => 'nextanative', 'title' => 'Idol Group', 'instagram' => 'nextanative', 'appearance_date' => $day2, 'transparent_background' => true],
            ['name' => "Andthrix's", 'slug' => 'andthrixs', 'title' => 'Idol Group', 'instagram' => 'andthrixs', 'appearance_date' => $day2, 'transparent_background' => true],
            ['name' => "Daruma Rollin'", 'slug' => 'daruma-rollin', 'title' => 'Girl Band', 'instagram' => null, 'appearance_date' => $day1, 'transparent_background' => false],
            ['name' => 'Acky Bright', 'slug' => 'acky-bright', 'title' => 'Comic Artist', 'instagram' => 'acky_bright', 'appearance_date' => $bothDays, 'transparent_background' => false],
            ['name' => 'Bryan Valenza', 'slug' => 'bryan-valenza', 'title' => 'Comic Artist', 'instagram' => 'bryan_valenza', 'appearance_date' => $bothDays, 'transparent_background' => true],
            ['name' => 'Redshift', 'slug' => 'redshift', 'title' => 'DJ Performance', 'instagram' => null, 'appearance_date' => null, 'transparent_background' => true],
            ['name' => 'Feel Koplo', 'slug' => 'feelkoplo', 'title' => 'DJ Performance', 'instagram' => 'feelkoplo', 'appearance_date' => $day2, 'transparent_background' => true],
            ['name' => 'Syncro Wotagei', 'slug' => 'wotagei', 'title' => 'Performer', 'instagram' => 'syncro_jkt', 'appearance_date' => $day1, 'transparent_background' => true],
        ];
    }
}
