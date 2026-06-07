<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Creates the Indonesia Anime Con (INACON) event so its website can show
 * programs / FAQ / gallery. INACON runs in conjunction with ICC; details come
 * from the pmone-events `apps/inacon` app.config. Poster is committed at
 * `database/seeders/event-posters/inacon.jpg`.
 *
 * Idempotent: skips if the inacon project already has this event. Run before the
 * programs/faq/gallery seeders.
 *
 * Run with: php artisan db:seed --class=InaconEventSeeder
 */
class InaconEventSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::where('username', 'inacon')->first();

        if (! $project) {
            $this->command?->warn('Skipping: inacon project not found.');

            return;
        }

        // Only create when inacon has NO event yet (production-safe: never
        // duplicate an event the team already created in the admin).
        if ($project->events()->exists()) {
            $this->command?->info('inacon already has event(s); skipping event creation.');

            return;
        }

        $event = $project->events()->create([
            'title' => 'myBCA Indonesia Comic Con x Indonesia Anime Con 2025',
            'description' => 'Festival anime dan budaya Jepang terbesar di Indonesia. Nikmati pengalaman seru bertemu guest artis, cosplay, merchandise eksklusif, dan banyak lagi.',
            'start_date' => Carbon::parse('2025-10-25 10:00:00'),
            'end_date' => Carbon::parse('2025-10-26 20:00:00'),
            'location' => 'Jakarta International Convention Center (JICC) Senayan',
            'location_link' => 'https://maps.app.goo.gl/iAyUVWEbUqHL1mGx7',
            'hall' => 'Assembly, Cendrawasih, and Plenary Hall',
            'status' => 'published',
            'visibility' => 'public',
            'is_active' => true,
        ]);

        $this->command?->info("Created event: {$event->title} ({$event->slug}).");

        $poster = database_path('seeders/event-posters/inacon.jpg');

        if (is_file($poster) && $event->getMedia('poster_image')->isEmpty()) {
            $event->addMedia($poster)->preservingOriginal()->toMediaCollection('poster_image');
            $this->command?->info('Attached poster image.');
        }
    }
}
