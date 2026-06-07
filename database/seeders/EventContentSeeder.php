<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Aggregates all event-content seeders in the correct order so production can
 * populate Programs / FAQ / Guests / Media Coverage / Gallery with a single command:
 *
 *     php artisan db:seed --class=EventContentSeeder
 *
 * InaconEventSeeder runs FIRST so the inacon event exists before its guests/
 * programs/faq/gallery are seeded (it self-skips if inacon already has an
 * event). All seeders are idempotent, so this is safe to re-run.
 */
class EventContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InaconEventSeeder::class,
            InaconGuestsSeeder::class,
            ProgramsSeeder::class,
            FaqsSeeder::class,
            MediaCoveragesSeeder::class,
            ProgramPhotosSeeder::class,
            EventGalleryPhotosSeeder::class,
        ]);
    }
}
