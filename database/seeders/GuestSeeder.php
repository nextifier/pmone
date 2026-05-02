<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Seeder;

class GuestSeeder extends Seeder
{
    public function run(): void
    {
        Event::all()->each(function (Event $event): void {
            Guest::factory()
                ->count(8)
                ->create(['event_id' => $event->id]);

            Guest::factory()
                ->featured()
                ->count(2)
                ->create(['event_id' => $event->id]);
        });
    }
}
