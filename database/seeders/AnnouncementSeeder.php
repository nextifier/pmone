<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    /**
     * Seed announcements that were previously hardcoded in DashboardAnnouncements.vue.
     */
    public function run(): void
    {
        $items = [
            [
                'title' => 'Rundown event sekarang bisa kamu upload sendiri.',
                'description' => 'Buka event yang kamu kelola, masuk ke tab Content lalu pilih Rundown. Buat rundown satu per satu sesuai jadwal acaramu.',
                'icon' => 'hugeicons:calendar-04',
                'type' => 'marketing',
                'status' => 'published',
                'is_global' => true,
                'is_dismissible' => true,
                'order_column' => 10,
                'cta_actions' => [
                    ['label' => 'Buka Events', 'url' => '/events', 'style' => 'link', 'icon' => null],
                ],
            ],
            [
                'title' => 'Visitor E-Guide juga sudah bisa kamu upload.',
                'description' => 'Dari halaman event, klik Edit Details lalu scroll ke section Visitor E-Guide. Upload file PDF kamu (max 20MB), terus simpan.',
                'icon' => 'hugeicons:book-open-01',
                'type' => 'marketing',
                'status' => 'published',
                'is_global' => true,
                'is_dismissible' => true,
                'order_column' => 20,
                'cta_actions' => [
                    ['label' => 'Buka Events', 'url' => '/events', 'style' => 'link', 'icon' => null],
                ],
            ],
        ];

        foreach ($items as $data) {
            Announcement::firstOrCreate(
                ['title' => $data['title']],
                $data
            );
        }
    }
}
