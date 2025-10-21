<?php

namespace Database\Seeders;

use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShortLinkSeeder extends Seeder
{
    private const DEFAULT_SHORT_LINKS_COUNT = 20;

    public function run(): void
    {
        $this->command->info('Creating short links...');

        // Only master, admin, and staff can create short links
        $eligibleUsers = User::role(['master', 'admin', 'staff'])->get();

        if ($eligibleUsers->isEmpty()) {
            $this->command->warn('No eligible users found to create short links. Skipping...');

            return;
        }

        $shortLinksCount = self::DEFAULT_SHORT_LINKS_COUNT;
        $bar = $this->command->getOutput()->createProgressBar($shortLinksCount);

        $slugs = [
            'registrasi-pengunjung', 'promo-2024', 'event-jakarta', 'kontak-sales',
            'katalog-produk', 'testimoni', 'partner-bisnis', 'karir',
            'panduan-user', 'faq-umum', 'download-brosur', 'webinar',
            'demo-produk', 'request-quote', 'success-story', 'case-study',
            'newsletter', 'blog-terbaru', 'update-sistem', 'promo-spesial',
        ];

        foreach ($slugs as $index => $slug) {
            if ($index >= $shortLinksCount) {
                break;
            }

            ShortLink::create([
                'user_id' => $eligibleUsers->random()->id,
                'slug' => $slug,
                'destination_url' => fake()->url(),
                'is_active' => fake()->boolean(90),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✅ Successfully created {$shortLinksCount} short links!");
    }
}
