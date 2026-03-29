<?php

namespace Database\Seeders;

use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Post;
use App\Models\Project;
use App\Models\ShortLink;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShowreelContentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'pion@pmone.id')->firstOrFail();
        $projects = Project::all();
        $allUsers = User::role(['master', 'admin', 'staff'])->get();

        $this->seedTasks($user, $projects, $allUsers);
        $this->seedShortLinks($user);
        $this->seedLinkPages($user);
        $this->seedPosts($user);

        $this->command->info('Showreel content seeding complete!');
    }

    private function seedTasks(User $user, $projects, $allUsers): void
    {
        $this->command->info('Creating tasks...');

        $taskTitles = [
            // Event preparation
            ['Finalisasi floorplan Megabuild 2026', 'high', 'high', 'in_progress'],
            ['Koordinasi vendor dekorasi Hall A', 'high', 'medium', 'in_progress'],
            ['Review kontrak sponsor utama FLEI 26th', 'high', 'high', 'todo'],
            ['Kirim undangan VIP untuk opening ceremony', 'medium', 'low', 'todo'],
            ['Setup sistem registrasi online pengunjung', 'high', 'high', 'in_progress'],
            ['Persiapan materi promosi media sosial', 'medium', 'medium', 'in_progress'],
            ['Konfirmasi jadwal pembicara seminar', 'high', 'medium', 'todo'],
            ['Cek ketersediaan parking area untuk exhibitor', 'low', 'low', 'todo'],
            ['Buat rundown acara opening day', 'medium', 'medium', 'todo'],
            ['Koordinasi security plan dengan venue', 'medium', 'medium', 'todo'],
            // Completed tasks
            ['Desain booth layout standard shell scheme', 'medium', 'high', 'completed'],
            ['Negosiasi harga catering untuk exhibitor lounge', 'medium', 'medium', 'completed'],
            ['Setup email blast template untuk exhibitor', 'low', 'low', 'completed'],
            ['Finalisasi daftar harga order form 2026', 'high', 'medium', 'completed'],
            ['Upload katalog produk ke sistem order', 'medium', 'low', 'completed'],
            ['Training tim sales untuk fitur baru PM One', 'medium', 'medium', 'completed'],
            ['Review dan approve desain poster CBE 8th', 'low', 'low', 'completed'],
            ['Buat SOP penanganan komplain exhibitor', 'medium', 'high', 'completed'],
            ['Integrasi payment gateway untuk online order', 'high', 'high', 'completed'],
            ['Setup analytics tracking untuk event website', 'medium', 'medium', 'completed'],
            // More todo/in_progress
            ['Follow up pembayaran exhibitor yang belum lunas', 'high', 'low', 'in_progress'],
            ['Perbarui informasi venue di semua event website', 'medium', 'low', 'todo'],
            ['Siapkan goodie bag untuk pengunjung VIP', 'low', 'low', 'todo'],
            ['Audit data exhibitor yang belum lengkap', 'medium', 'medium', 'in_progress'],
            ['Buat template badge untuk setiap event', 'medium', 'low', 'in_progress'],
            ['Koordinasi dengan tim IT untuk WiFi venue', 'medium', 'medium', 'todo'],
            ['Persiapan dokumentasi foto dan video event', 'low', 'low', 'todo'],
            ['Review feedback exhibitor dari event sebelumnya', 'medium', 'medium', 'todo'],
            ['Update price list electricity dan internet', 'low', 'low', 'in_progress'],
            ['Buat proposal kerjasama media partner', 'medium', 'medium', 'todo'],
            // Overdue
            ['Kirim invoice exhibitor batch Maret', 'high', 'low', 'todo'],
            ['Deadline submission materi promosi exhibitor', 'high', 'medium', 'todo'],
        ];

        foreach ($taskTitles as $index => [$title, $priority, $complexity, $status]) {
            $project = $projects->random();
            $assignee = fake()->boolean(70) ? $allUsers->random() : null;

            $estimatedStart = match ($status) {
                'completed' => fake()->dateTimeBetween('-2 months', '-2 weeks'),
                'in_progress' => fake()->dateTimeBetween('-2 weeks', 'now'),
                default => fake()->optional(0.6)->dateTimeBetween('now', '+1 month'),
            };

            $estimatedCompletion = match ($status) {
                'completed' => fake()->dateTimeBetween('-2 weeks', '-1 day'),
                'in_progress' => fake()->dateTimeBetween('+1 day', '+3 weeks'),
                default => $index >= 30
                    ? fake()->dateTimeBetween('-1 week', '-1 day') // overdue
                    : fake()->optional(0.7)->dateTimeBetween('+3 days', '+2 months'),
            };

            Task::create([
                'title' => $title,
                'description' => fake()->optional(0.6)->paragraphs(fake()->numberBetween(1, 3), true),
                'status' => $status,
                'priority' => $priority,
                'complexity' => $complexity,
                'visibility' => fake()->randomElement(['public', 'public', 'private']),
                'project_id' => $project->id,
                'assignee_id' => $assignee?->id,
                'estimated_start_at' => $estimatedStart,
                'estimated_completion_at' => $estimatedCompletion,
                'completed_at' => $status === 'completed' ? fake()->dateTimeBetween('-2 weeks', 'now') : null,
                'created_by' => $user->id,
            ]);
        }

        $this->command->line('  Tasks: '.count($taskTitles).' created');
    }

    private function seedShortLinks(User $user): void
    {
        $this->command->info('Creating short links...');

        $links = [
            ['megabuild-2026', 'https://megabuild.co.id', 'Megabuild Indonesia 2026', 'Pameran konstruksi dan building material terbesar di Indonesia'],
            ['flei-26', 'https://flei.co.id', 'FLEI 26th Edition', 'Franchise & License Expo Indonesia'],
            ['cbe-jakarta', 'https://cafebrasserieexpo.com', 'Cafe & Brasserie Expo Jakarta', 'Pameran kafe dan restoran terlengkap'],
            ['icc-2026', 'https://indonesiacomiccon.com', 'Indonesia Comic Con 2026', 'Festival pop culture terbesar di Indonesia'],
            ['keramika-2026', 'https://keramika.co.id', 'Keramika Indonesia 2026', 'Pameran keramik dan tile internasional'],
            ['morefood-expo', 'https://morefoodexpo.com', 'MoreFood Expo Indonesia', 'Pameran makanan dan minuman'],
            ['renex-2026', 'https://renovationexpo.co.id', 'Renovation Expo 2026', 'Pameran renovasi dan interior design'],
            ['ioe-2026', 'https://ioe.co.id', 'IOE 2026', 'Indonesia Outing & Incentive Travel Expo'],
            ['daftar-exhibitor', 'https://pmone.id/register-exhibitor', 'Daftar Jadi Exhibitor', 'Form pendaftaran exhibitor untuk semua event'],
            ['visitor-registration', 'https://pmone.id/visitor', 'Registrasi Pengunjung', 'Daftar sebagai pengunjung pameran'],
            ['katalog-2026', 'https://pmone.id/catalog/2026', 'Katalog Event 2026', 'Lihat semua event tahun 2026'],
            ['kontak-sales', 'https://pmone.id/contact', 'Hubungi Sales Team', 'Konsultasi booth dan sponsorship'],
            ['download-proposal', 'https://pmone.id/downloads/proposal', 'Download Proposal', 'Proposal sponsorship dan partnership'],
            ['floorplan-megabuild', 'https://pmone.id/floorplan/megabuild-2026', 'Floorplan Megabuild 2026', 'Lihat layout hall dan booth availability'],
            ['promo-earlybird', 'https://pmone.id/promo/early-bird', 'Promo Early Bird', 'Diskon 20% untuk booking booth sebelum April'],
            ['media-partner', 'https://pmone.id/media-partner', 'Jadi Media Partner', 'Form registrasi media partner'],
            ['exhibitor-manual', 'https://pmone.id/manual/exhibitor', 'Exhibitor Manual Book', 'Panduan lengkap untuk exhibitor'],
            ['order-form', 'https://pmone.id/order', 'Order Form Online', 'Pesan layanan tambahan untuk booth Anda'],
            ['testimoni-exhibitor', 'https://pmone.id/testimonials', 'Testimoni Exhibitor', 'Apa kata exhibitor tentang event kami'],
            ['newsletter', 'https://pmone.id/newsletter', 'Subscribe Newsletter', 'Update terbaru seputar event PM One'],
            ['social-ig', 'https://instagram.com/pmone.id', 'Instagram PM One', 'Follow kami di Instagram'],
            ['social-linkedin', 'https://linkedin.com/company/pmone', 'LinkedIn PM One', 'Connect with us on LinkedIn'],
            ['wa-sales', 'https://wa.me/6281234567890', 'WhatsApp Sales', 'Chat langsung dengan tim sales'],
            ['faq', 'https://pmone.id/faq', 'FAQ', 'Pertanyaan yang sering diajukan'],
            ['career', 'https://pmone.id/career', 'Lowongan Kerja', 'Bergabung dengan tim PM One'],
        ];

        foreach ($links as [$slug, $url, $ogTitle, $ogDesc]) {
            if (ShortLink::where('slug', $slug)->exists()) {
                continue;
            }

            ShortLink::create([
                'user_id' => $user->id,
                'slug' => $slug,
                'destination_url' => $url,
                'og_title' => fake()->boolean(70) ? $ogTitle : null,
                'og_description' => fake()->boolean(50) ? $ogDesc : null,
                'og_type' => 'website',
                'is_active' => fake()->boolean(90),
                'created_by' => $user->id,
            ]);
        }

        $this->command->line('  Short links: '.count($links).' created');
    }

    private function seedLinkPages(User $user): void
    {
        $this->command->info('Creating link pages...');

        $pages = [
            [
                'slug' => 'pmone-events',
                'title' => 'PM One Events 2026',
                'description' => 'Semua event yang dikelola PM One tahun 2026',
                'items' => [
                    ['Megabuild Indonesia 2026', 'https://megabuild.co.id', 'Pameran konstruksi terbesar'],
                    ['FLEI 26th Edition', 'https://flei.co.id', 'Franchise & License Expo'],
                    ['Cafe & Brasserie Expo 8th', 'https://cafebrasserieexpo.com', 'Pameran kafe dan restoran'],
                    ['Keramika Indonesia 2026', 'https://keramika.co.id', 'Pameran keramik internasional'],
                    ['Indonesia Comic Con 2026', 'https://indonesiacomiccon.com', 'Festival pop culture'],
                    ['MoreFood Expo', 'https://morefoodexpo.com', 'Pameran F&B terlengkap'],
                    ['IOE 2026', 'https://ioe.co.id', 'Outing & Incentive Travel Expo'],
                ],
            ],
            [
                'slug' => 'exhibitor-resources',
                'title' => 'Exhibitor Resources',
                'description' => 'Semua yang dibutuhkan exhibitor',
                'items' => [
                    ['Exhibitor Manual Book', 'https://pmone.id/manual/exhibitor', 'Panduan lengkap exhibitor'],
                    ['Order Form Online', 'https://pmone.id/order', 'Pesan layanan booth tambahan'],
                    ['Floorplan & Booth Map', 'https://pmone.id/floorplan', 'Layout hall dan posisi booth'],
                    ['Promotion Post Guidelines', 'https://pmone.id/promo-guide', 'Panduan upload materi promosi'],
                    ['FAQ Exhibitor', 'https://pmone.id/faq/exhibitor', 'Pertanyaan umum exhibitor'],
                    ['Contact Support', 'https://pmone.id/support', 'Hubungi tim support'],
                ],
            ],
            [
                'slug' => 'sponsor-partner',
                'title' => 'Sponsorship & Partnership',
                'description' => 'Peluang kerjasama dengan PM One Events',
                'items' => [
                    ['Sponsorship Proposal 2026', 'https://pmone.id/proposal/2026', 'Download proposal sponsorship'],
                    ['Media Partner Registration', 'https://pmone.id/media-partner', 'Daftar sebagai media partner'],
                    ['Association Partner', 'https://pmone.id/association', 'Kerjasama dengan asosiasi'],
                    ['Success Stories', 'https://pmone.id/success-stories', 'Cerita sukses partner kami'],
                    ['Contact Partnership Team', 'https://pmone.id/contact/partnership', 'Hubungi tim partnership'],
                ],
            ],
            [
                'slug' => 'pmone-social',
                'title' => 'PM One Social Media',
                'description' => 'Follow dan connect dengan kami',
                'items' => [
                    ['Instagram', 'https://instagram.com/pmone.id', '@pmone.id'],
                    ['LinkedIn', 'https://linkedin.com/company/pmone', 'PT Panorama Media'],
                    ['YouTube', 'https://youtube.com/@pmone', 'PM One Channel'],
                    ['TikTok', 'https://tiktok.com/@pmone.id', '@pmone.id'],
                    ['Facebook', 'https://facebook.com/pmone.id', 'PM One Events'],
                    ['Website', 'https://pmone.id', 'pmone.id'],
                    ['WhatsApp', 'https://wa.me/6281234567890', 'Chat with us'],
                ],
            ],
            [
                'slug' => 'megabuild-links',
                'title' => 'Megabuild Indonesia 2026',
                'description' => 'All links for Megabuild Indonesia 2026',
                'items' => [
                    ['Event Website', 'https://megabuild.co.id', 'Official website'],
                    ['Visitor Registration', 'https://megabuild.co.id/register', 'Daftar sebagai pengunjung'],
                    ['Exhibitor List', 'https://megabuild.co.id/exhibitors', 'Lihat daftar exhibitor'],
                    ['Event Schedule', 'https://megabuild.co.id/schedule', 'Jadwal acara dan seminar'],
                    ['Venue Map', 'https://megabuild.co.id/venue', 'Peta lokasi dan parkir'],
                    ['Instagram', 'https://instagram.com/megabuild.id', '@megabuild.id'],
                ],
            ],
        ];

        foreach ($pages as $pageData) {
            if (LinkPage::where('slug', $pageData['slug'])->exists()) {
                continue;
            }

            $page = LinkPage::create([
                'user_id' => $user->id,
                'slug' => $pageData['slug'],
                'title' => $pageData['title'],
                'description' => $pageData['description'],
                'is_active' => true,
                'visibility' => 'public',
                'created_by' => $user->id,
            ]);

            foreach ($pageData['items'] as $order => [$label, $url, $desc]) {
                LinkPageItem::create([
                    'link_page_id' => $page->id,
                    'label' => $label,
                    'url' => $url,
                    'description' => $desc,
                    'is_active' => true,
                    'sort_order' => $order + 1,
                    'created_by' => $user->id,
                ]);
            }
        }

        $this->command->line('  Link pages: '.count($pages).' created');
    }

    private function seedPosts(User $user): void
    {
        $this->command->info('Creating posts...');

        $posts = [
            // Published posts
            ['Megabuild Indonesia 2026 Siap Digelar, Targetkan 15.000 Pengunjung', 'published', true],
            ['Franchise & License Expo Indonesia Kembali Hadir untuk Edisi ke-26', 'published', false],
            ['Tips Memilih Lokasi Booth yang Strategis di Pameran', 'published', false],
            ['Panduan Lengkap Order Form untuk Exhibitor Baru', 'published', true],
            ['5 Strategi Marketing Efektif untuk Exhibitor Pameran', 'published', false],
            ['Keramika Indonesia 2026: Showcase Tren Keramik Terbaru', 'published', false],
            ['Cafe & Brasserie Expo Jakarta 8th: Lebih Besar dan Lebih Lengkap', 'published', false],
            ['Indonesia Comic Con 2026: Pengalaman Pop Culture Terlengkap', 'published', true],
            ['MoreFood Expo Indonesia: Surga Bagi Pelaku Industri F&B', 'published', false],
            ['Cara Maksimalkan ROI dari Partisipasi Pameran', 'published', false],
            ['Renovation Expo Sukses Hadirkan 122 Exhibitor di 2025', 'published', false],
            ['Indonesia Outing Expo: Tren Wisata Korporat 2026', 'published', false],
            ['PM One Luncurkan Fitur Online Order Form untuk Exhibitor', 'published', true],
            ['Interview: CEO PT Semen Indonesia Bicara Soal Megabuild', 'published', false],
            ['Daftar Lengkap Event PM One Tahun 2026', 'published', false],
            ['Kenapa Harus Ikut Pameran? Ini 7 Alasannya', 'published', false],
            ['Update Floorplan Megabuild 2026: Hall A1-A3 Sudah 75% Terisi', 'published', false],
            ['Media Partner Spotlight: Kerjasama dengan 50+ Media Nasional', 'published', false],
            ['Success Story: Exhibitor FLEI yang Raih 200+ Leads dalam 3 Hari', 'published', false],
            ['Promo Early Bird untuk Semua Event 2026 Berakhir April Ini', 'published', false],
            // Draft posts
            ['Draft: Proposal Sponsorship Package 2027', 'draft', false],
            ['Draft: Recap Video Script untuk Showreel', 'draft', false],
            ['Draft: Annual Report PM One Events 2025', 'draft', false],
            ['Draft: Partnership dengan Asosiasi Baru', 'draft', false],
            ['Draft: Exhibitor Satisfaction Survey Results', 'draft', false],
            // Scheduled posts
            ['Coming Soon: FLEI 26th Early Bird Promo Details', 'scheduled', false],
            ['Coming Soon: ICC 2026 Guest Star Announcement', 'scheduled', true],
            ['Coming Soon: Keramika 2026 International Pavilion Preview', 'scheduled', false],
        ];

        $tags = [
            'event', 'pameran', 'exhibitor', 'tips', 'marketing',
            'franchise', 'konstruksi', 'f&b', 'pop-culture', 'interior',
            'promo', 'partnership', 'update', 'success-story', 'panduan',
        ];

        foreach ($posts as [$title, $status, $featured]) {
            $content = $this->generatePostContent($title);

            $post = Post::create([
                'title' => $title,
                'excerpt' => fake()->paragraph(2),
                'content' => $content,
                'content_format' => 'html',
                'status' => $status,
                'visibility' => $status === 'draft' ? 'private' : fake()->randomElement(['public', 'public', 'public', 'members_only']),
                'published_at' => match ($status) {
                    'published' => fake()->dateTimeBetween('-6 months', 'now'),
                    'scheduled' => fake()->dateTimeBetween('+1 day', '+1 month'),
                    default => null,
                },
                'featured' => $featured,
                'source' => 'native',
                'created_by' => $user->id,
            ]);

            $postTags = fake()->randomElements($tags, fake()->numberBetween(2, 5));
            $post->syncPostTags($postTags);
        }

        $this->command->line('  Posts: '.count($posts).' created');
    }

    private function generatePostContent(string $title): string
    {
        $paragraphs = fake()->paragraphs(fake()->numberBetween(4, 8));
        $html = '<h2>'.fake()->sentence(4).'</h2>';

        foreach ($paragraphs as $i => $p) {
            $html .= '<p>'.$p.'</p>';
            if ($i === 1) {
                $html .= '<h3>'.fake()->sentence(3).'</h3>';
            }
            if ($i === 3) {
                $html .= '<blockquote><p>'.fake()->sentence(8).'</p></blockquote>';
            }
        }

        $html .= '<h3>'.fake()->sentence(3).'</h3>';
        $html .= '<ul>';
        for ($i = 0; $i < fake()->numberBetween(3, 6); $i++) {
            $html .= '<li>'.fake()->sentence(6).'</li>';
        }
        $html .= '</ul>';
        $html .= '<p>'.fake()->paragraph(3).'</p>';

        return $html;
    }
}
