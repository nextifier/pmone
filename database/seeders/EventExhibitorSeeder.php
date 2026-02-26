<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventExhibitorSeeder extends Seeder
{
    /**
     * Event definitions per project username.
     * Each entry: [title_suffix, edition_number, year, month, days_duration, location, hall]
     */
    private array $eventDefinitions = [
        'megabuild' => [
            // Already has edition 23 (2026) - add 2-4 more
            ['2025', 22, 2025, 6, 4, 'Jakarta International Expo, Kemayoran', 'Hall A1-A3'],
            ['2024', 21, 2024, 6, 4, 'Jakarta International Expo, Kemayoran', 'Hall A1-A3'],
            ['2027', 24, 2027, 6, 4, 'Jakarta International Expo, Kemayoran', 'Hall A1-A3'],
        ],
        'keramika' => [
            ['2026', 15, 2026, 9, 3, 'Jakarta International Expo, Kemayoran', 'Hall B1-B2'],
            ['2025', 14, 2025, 9, 3, 'Jakarta International Expo, Kemayoran', 'Hall B1-B2'],
            ['2024', 13, 2024, 9, 3, 'Jakarta International Expo, Kemayoran', 'Hall B1-B2'],
        ],
        'flei' => [
            ['2026', 19, 2026, 3, 3, 'Jakarta Convention Center, Senayan', 'Hall A'],
            ['2025', 18, 2025, 3, 3, 'Jakarta Convention Center, Senayan', 'Hall A'],
            ['2024', 17, 2024, 3, 3, 'Jakarta Convention Center, Senayan', 'Hall A'],
            ['2027', 20, 2027, 3, 3, 'Jakarta Convention Center, Senayan', 'Hall A'],
        ],
        'cbe' => [
            ['2026', 8, 2026, 5, 3, 'Jakarta International Expo, Kemayoran', 'Hall C1'],
            ['2025', 7, 2025, 5, 3, 'Jakarta International Expo, Kemayoran', 'Hall C1'],
            ['2024', 6, 2024, 5, 3, 'Jakarta International Expo, Kemayoran', 'Hall C1'],
        ],
        'icf' => [
            ['2026', 10, 2026, 8, 3, 'ICE BSD, Tangerang', 'Hall 5'],
            ['2025', 9, 2025, 8, 3, 'ICE BSD, Tangerang', 'Hall 5'],
            ['2024', 8, 2024, 8, 3, 'ICE BSD, Tangerang', 'Hall 5'],
            ['2027', 11, 2027, 8, 3, 'ICE BSD, Tangerang', 'Hall 5'],
        ],
        'cei' => [
            ['2026', 5, 2026, 8, 3, 'ICE BSD, Tangerang', 'Hall 6'],
            ['2025', 4, 2025, 8, 3, 'ICE BSD, Tangerang', 'Hall 6'],
        ],
        'morefood' => [
            ['2026', 7, 2026, 11, 3, 'Jakarta International Expo, Kemayoran', 'Hall A'],
            ['2025', 6, 2025, 11, 3, 'Jakarta International Expo, Kemayoran', 'Hall A'],
            ['2024', 5, 2024, 11, 3, 'Jakarta International Expo, Kemayoran', 'Hall A'],
        ],
        'renex' => [
            ['2026', 4, 2026, 6, 4, 'Jakarta International Expo, Kemayoran', 'Hall B'],
            ['2025', 3, 2025, 6, 4, 'Jakarta International Expo, Kemayoran', 'Hall B'],
            ['2024', 2, 2024, 6, 4, 'Jakarta International Expo, Kemayoran', 'Hall B'],
        ],
        'ioe' => [
            ['2026', 12, 2026, 4, 3, 'Jakarta Convention Center, Senayan', 'Hall B'],
            ['2025', 11, 2025, 4, 3, 'Jakarta Convention Center, Senayan', 'Hall B'],
            ['2027', 13, 2027, 4, 3, 'Jakarta Convention Center, Senayan', 'Hall B'],
        ],
        'icc' => [
            ['2026', 6, 2026, 10, 3, 'ICE BSD, Tangerang', 'Hall 3-4'],
            ['2025', 5, 2025, 10, 3, 'ICE BSD, Tangerang', 'Hall 3-4'],
            ['2024', 4, 2024, 10, 3, 'ICE BSD, Tangerang', 'Hall 3-4'],
            ['2027', 7, 2027, 10, 3, 'ICE BSD, Tangerang', 'Hall 3-4'],
            ['2023', 3, 2023, 10, 3, 'ICE BSD, Tangerang', 'Hall 3-4'],
        ],
        'inacon' => [
            ['2026', 4, 2026, 10, 3, 'ICE BSD, Tangerang', 'Hall 5-6'],
            ['2025', 3, 2025, 10, 3, 'ICE BSD, Tangerang', 'Hall 5-6'],
            ['2024', 2, 2024, 10, 3, 'ICE BSD, Tangerang', 'Hall 5-6'],
        ],
    ];

    /**
     * Product catalog templates per event type.
     */
    private array $productTemplates = [
        'exhibition' => [
            ['Layanan Listrik', 'Instalasi Listrik 1300W', 650000, 'unit'],
            ['Layanan Listrik', 'Instalasi Listrik 2200W', 1100000, 'unit'],
            ['Layanan Listrik', 'Instalasi Listrik 3500W', 1750000, 'unit'],
            ['Audio Visual', 'LED TV 55 inch', 3500000, 'unit'],
            ['Audio Visual', 'Sound System Paket Standar', 2500000, 'set'],
            ['Audio Visual', 'Spotlight 100W', 350000, 'unit'],
            ['Furnitur', 'Meja Display 120x60cm', 450000, 'unit'],
            ['Furnitur', 'Kursi Lipat', 75000, 'unit'],
            ['Furnitur', 'Rak Display 5 Tingkat', 850000, 'unit'],
            ['Furnitur', 'Kabinet Penyimpanan', 650000, 'unit'],
            ['Internet & Telekomunikasi', 'WiFi Dedicated 10 Mbps', 2500000, 'unit'],
            ['Internet & Telekomunikasi', 'WiFi Dedicated 20 Mbps', 4000000, 'unit'],
            ['Dekorasi', 'Karpet per m2', 85000, 'sqm'],
            ['Dekorasi', 'Backdrop Printing per m2', 250000, 'sqm'],
            ['Dekorasi', 'Fascia Name Board', 500000, 'unit'],
        ],
    ];

    /**
     * Exhibitor company names pool - realistic Indonesian companies.
     */
    private array $exhibitorNames = [
        // Construction & Building
        'PT Semen Indonesia', 'PT Holcim Indonesia', 'PT Beton Jaya Manunggal', 'PT Arwana Citramulia',
        'PT Surya Toto Indonesia', 'PT Mulia Industrindo', 'PT Cahaya Sakti Furintraco', 'PT Kedaung Indah Can',
        'PT Intikeramik Alamasri', 'PT Cakra Compact Aluminium', 'PT Alumindo Light Metal',
        // F&B
        'PT Sari Roti', 'PT Mayora Indah', 'PT Garudafood Putra Putri Jaya', 'PT Nippon Indosari Corpindo',
        'PT Ultrajaya Milk', 'PT Diamond Food Indonesia', 'PT Siantar Top', 'PT Sekar Laut',
        'PT Prashida Utama', 'PT Tiga Pilar Sejahtera Food', 'PT Wilmar Cahaya Indonesia',
        'PT Santos Jaya Abadi', 'PT Java Prima Abadi', 'PT Tanobel Food', 'PT Kino Indonesia',
        // Coffee & Chocolate
        'PT Kapal Api Global', 'PT Torabika Eka Semesta', 'PT JDE Peet\'s Indonesia', 'Anomali Coffee',
        'Kopi Kenangan', 'Fore Coffee', 'Filosofi Kopi', 'Djournal Coffee', 'Kopikalyan',
        'PT Cocoa Ventures Indonesia', 'PT Barry Callebaut Indonesia', 'Pipiltin Cocoa', 'Krakakoa',
        'Monggo Chocolate', 'PT Ceres Indonesia',
        // Franchise
        'PT Sour Sally Group', 'PT Baba Rafi Indonesia', 'Kebab Turki Baba Rafi', 'Kopi Janji Jiwa',
        'Es Teh Indonesia', 'Mixue Indonesia', 'PT Richeese Kuliner Indonesia', 'Hokben',
        'Geprek Bensu', 'Ayam Keprabon', 'PT Bumi Berkah Boga', 'Haus! Indonesia',
        // Travel & Events
        'PT Panorama JTB Tours', 'PT Antavaya Tour & Travel', 'PT Bayu Buana Travel',
        'PT Dwidaya World Wide', 'PT Smailing Tour', 'PT Wita Tour', 'PT Anta Tour',
        'PT MG Holiday', 'PT Golden Rama Express', 'PT Marintur Indonesia',
        // Entertainment & Pop Culture
        'PT Elex Media Komputindo', 'PT Gramedia Pustaka Utama', 'Level Up Indonesia',
        'Koloni Manga Studio', 'Caravan Studio', 'PT MNC Pictures', 'RE:ON Comics',
        'Animonsta Studios', 'PT Pop Culture Asia', 'Digital Happiness',
        'Mojiken Studio', 'Toge Productions', 'Agate International', 'Dreamax Studio',
        'PT Sun Star Motor', 'PT Bandai Namco Indonesia',
        // Home & Renovation
        'PT Mitra Adiperkasa', 'PT Ace Hardware Indonesia', 'PT Kawan Lama Sejahtera',
        'IKEA Indonesia', 'PT Informa Furnishings', 'PT Ruparupa', 'PT Dekoruma',
        'PT Dulux Indonesia', 'PT Nippon Paint Indonesia', 'PT Jotun Indonesia',
        // Generic
        'PT Maju Jaya Sentosa', 'PT Karya Utama Indonesia', 'PT Global Mandiri Teknik',
        'PT Prima Sukses Makmur', 'PT Bintang Sejahtera', 'PT Mega Karya Nusantara',
        'PT Sinar Mutiara Abadi', 'PT Trisula Textile Industries', 'PT Sumber Alfaria Trijaya',
        'PT Indo Tambangraya Megah', 'PT Astra Graphia', 'PT Sarana Menara Nusantara',
        'PT Jasa Marga', 'PT Waskita Karya', 'PT PP Properti', 'PT Adhi Karya',
        'PT Total Bangun Persada', 'PT Nusa Raya Cipta', 'PT Wijaya Karya',
        'PT Ciputra Development', 'PT Lippo Karawaci', 'PT Pakuwon Jati',
        'PT Sumber Daya Nusantara', 'PT Kreasi Teknologi Indonesia', 'PT Digital Nusantara',
        'PT Optima Solusi Kreatif', 'PT Mandala Multifinance', 'PT Metro Realty',
        'PT Harmoni Dinamika', 'PT Citra Abadi Sejati', 'PT Perdana Gapura Prima',
        'PT Indomobil Sukses Internasional', 'PT Modernland Realty', 'PT Summarecon Agung',
        'PT Cikarang Listrindo', 'PT Bakrie & Brothers', 'PT Delta Dunia Makmur',
        'PT Bumi Resources', 'PT Indika Energy', 'PT Medco Energi Internasional',
        'PT Surya Esa Perkasa', 'PT Energi Mega Persada', 'PT Perusahaan Gas Negara',
        'CV Berkat Usaha Mandiri', 'CV Sumber Makmur', 'CV Cipta Karya',
        'CV Teknologi Prima', 'CV Kreasi Mandiri', 'PT Solusi Digital Nusantara',
        'PT Buana Lintas Lautan', 'PT Samudera Indonesia', 'PT Temas Lestari',
        'PT Siwani Makmur', 'PT Indo Kordsa', 'PT Goodyear Indonesia',
        'PT Gajah Tunggal', 'PT Multistrada Arah Sarana',
    ];

    public function run(): void
    {
        $this->command->info('Seeding events, exhibitors, and orders...');

        $creator = User::role('master')->first();

        if (! $creator) {
            $this->command->error('No master user found. Aborting.');

            return;
        }

        // Create a pool of brands to be reused across events
        $this->command->info('Creating brand pool...');
        $allBrands = $this->createBrandPool($creator);
        $this->command->info('Created '.count($allBrands).' brands.');

        // Seed events for each project
        foreach ($this->eventDefinitions as $username => $events) {
            $project = Project::where('username', $username)->first();

            if (! $project) {
                $this->command->warn("Project '{$username}' not found. Skipping.");

                continue;
            }

            $this->command->info("Seeding events for {$project->name}...");

            foreach ($events as $eventDef) {
                [$yearSuffix, $edition, $year, $month, $days, $location, $hall] = $eventDef;

                $title = "{$project->name} {$yearSuffix}";

                // Skip if event already exists
                if (Event::where('project_id', $project->id)->where('title', $title)->exists()) {
                    $this->command->line("  - Skipped (exists): {$title}");

                    continue;
                }

                $event = $this->createEvent($project, $title, $edition, $year, $month, $days, $location, $hall, $creator);
                $this->command->line("  + Created: {$title} (edition {$edition})");

                // Create event products
                $products = $this->createEventProducts($event, $creator);

                // Determine exhibitor count based on event type and edition
                $exhibitorCount = $this->getExhibitorCount($username, $edition);

                // Assign random brands as exhibitors
                $selectedBrands = $allBrands->random(min($exhibitorCount, count($allBrands)));
                $brandEvents = $this->assignExhibitors($event, $selectedBrands, $creator);
                $this->command->line("    Exhibitors: {$brandEvents->count()}");

                // Create orders for some exhibitors
                $orderCount = $this->createOrders($brandEvents, $products, $creator);
                $this->command->line("    Orders: {$orderCount}");
            }
        }

        // Set latest event per project as active
        foreach ($this->eventDefinitions as $username => $events) {
            $project = Project::where('username', $username)->first();
            if (! $project) {
                continue;
            }

            $latestEvent = Event::where('project_id', $project->id)
                ->orderByRaw('start_date IS NULL, start_date DESC')
                ->orderByDesc('id')
                ->first();

            $latestEvent?->update(['is_active' => true]);
        }

        $this->command->info('Seeding complete!');
    }

    private function createBrandPool(User $creator): \Illuminate\Support\Collection
    {
        $existingBrands = Brand::all();
        $existingSlugs = $existingBrands->pluck('slug')->toArray();
        $brandsToCreate = [];

        foreach ($this->exhibitorNames as $name) {
            $slug = \Illuminate\Support\Str::slug($name);

            if (in_array($slug, $existingSlugs)) {
                continue;
            }

            $brandsToCreate[] = $name;
            $existingSlugs[] = $slug;
        }

        $newBrands = collect();
        foreach ($brandsToCreate as $name) {
            $brand = Brand::create([
                'name' => $name,
                'description' => fake()->optional(0.6)->paragraph(),
                'company_name' => $name,
                'company_address' => fake()->address(),
                'company_email' => fake()->companyEmail(),
                'company_phone' => fake()->phoneNumber(),
                'status' => 'active',
                'visibility' => fake()->randomElement(['public', 'private']),
                'created_by' => $creator->id,
            ]);
            $newBrands->push($brand);
        }

        return Brand::where('status', 'active')->get();
    }

    private function createEvent(
        Project $project,
        string $title,
        int $edition,
        int $year,
        int $month,
        int $days,
        string $location,
        string $hall,
        User $creator,
    ): Event {
        $startDay = fake()->numberBetween(5, 20);
        $startDate = \Carbon\Carbon::create($year, $month, $startDay, 10, 0, 0);
        $endDate = (clone $startDate)->addDays($days)->setTime(18, 0);

        // Past events = published, future events = draft or published
        $isPast = $startDate->isPast();
        $status = $isPast ? 'published' : fake()->randomElement(['draft', 'published', 'published']);
        $visibility = $status === 'published' ? 'public' : 'private';

        return Event::create([
            'project_id' => $project->id,
            'title' => $title,
            'edition_number' => $edition,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'location' => $location,
            'hall' => $hall,
            'status' => $status,
            'visibility' => $visibility,
            'gross_area' => fake()->randomFloat(2, 1000, 15000),
            'is_active' => false,
            'order_form_deadline' => $isPast ? null : $startDate->copy()->subDays(30),
            'promotion_post_deadline' => $isPast ? null : $startDate->copy()->subDays(14),
            'created_by' => $creator->id,
        ]);
    }

    private function createEventProducts(Event $event, User $creator): \Illuminate\Support\Collection
    {
        $products = collect();

        foreach ($this->productTemplates['exhibition'] as $index => [$category, $name, $price, $unit]) {
            // Randomize price a bit (+/- 20%)
            $adjustedPrice = $price * fake()->randomFloat(2, 0.8, 1.2);

            $product = EventProduct::create([
                'event_id' => $event->id,
                'category' => $category,
                'name' => $name,
                'price' => round($adjustedPrice, -3), // Round to nearest thousand
                'unit' => $unit,
                'is_active' => true,
                'order_column' => $index + 1,
                'created_by' => $creator->id,
            ]);
            $products->push($product);
        }

        return $products;
    }

    private function getExhibitorCount(string $projectUsername, int $edition): int
    {
        // Bigger/older events get more exhibitors
        return match ($projectUsername) {
            'megabuild' => fake()->numberBetween(80, 150),
            'icc', 'inacon' => fake()->numberBetween(40, 100),
            'flei' => fake()->numberBetween(60, 120),
            'icf', 'cbe' => fake()->numberBetween(40, 90),
            'keramika' => fake()->numberBetween(50, 100),
            'morefood' => fake()->numberBetween(40, 80),
            'renex' => fake()->numberBetween(30, 70),
            'ioe' => fake()->numberBetween(20, 60),
            'cei' => fake()->numberBetween(20, 50),
            default => fake()->numberBetween(20, 60),
        };
    }

    private function assignExhibitors(Event $event, \Illuminate\Support\Collection $brands, User $creator): \Illuminate\Support\Collection
    {
        $salesUsers = User::role(['staff', 'admin'])->pluck('id')->toArray();
        $brandEvents = collect();
        $boothCounter = 1;

        foreach ($brands as $brand) {
            // Skip if already assigned
            if (BrandEvent::where('brand_id', $brand->id)->where('event_id', $event->id)->exists()) {
                continue;
            }

            $boothType = fake()->randomElement(['raw_space', 'standard_shell_scheme', 'enhanced_shell_scheme']);
            $boothSize = match ($boothType) {
                'raw_space' => fake()->randomElement([18, 27, 36, 54, 72, 108]),
                'standard_shell_scheme' => fake()->randomElement([9, 12, 15, 18]),
                'enhanced_shell_scheme' => fake()->randomElement([9, 12, 15, 18, 24]),
            };
            $boothPrice = match ($boothType) {
                'raw_space' => $boothSize * fake()->numberBetween(1500000, 2500000),
                'standard_shell_scheme' => $boothSize * fake()->numberBetween(2500000, 4000000),
                'enhanced_shell_scheme' => $boothSize * fake()->numberBetween(3500000, 5500000),
            };

            $status = fake()->randomElement(['confirmed', 'confirmed', 'confirmed', 'draft', 'active']);

            $brandEvent = BrandEvent::create([
                'brand_id' => $brand->id,
                'event_id' => $event->id,
                'booth_number' => sprintf('%s-%03d', fake()->randomElement(['A', 'B', 'C', 'D']), $boothCounter),
                'booth_size' => $boothSize,
                'booth_price' => $boothPrice,
                'booth_type' => $boothType,
                'sales_id' => ! empty($salesUsers) ? fake()->randomElement($salesUsers) : null,
                'status' => $status,
                'promotion_post_limit' => fake()->randomElement([1, 2, 3]),
            ]);

            $brandEvents->push($brandEvent);
            $boothCounter++;
        }

        return $brandEvents;
    }

    private function createOrders(\Illuminate\Support\Collection $brandEvents, \Illuminate\Support\Collection $products, User $creator): int
    {
        $orderCount = 0;

        foreach ($brandEvents as $brandEvent) {
            // ~40% of exhibitors have orders
            if (fake()->boolean(40) === false) {
                continue;
            }

            // 1-3 orders per exhibitor
            $numOrders = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numOrders; $i++) {
                $order = Order::create([
                    'brand_event_id' => $brandEvent->id,
                    'status' => fake()->randomElement(['submitted', 'submitted', 'confirmed', 'cancelled']),
                    'notes' => fake()->optional(0.2)->sentence(),
                    'subtotal' => 0,
                    'tax_rate' => 11.00,
                    'tax_amount' => 0,
                    'total' => 0,
                    'submitted_at' => fake()->dateTimeBetween('-3 months', 'now'),
                    'confirmed_at' => null,
                    'created_by' => $creator->id,
                ]);

                if ($order->status === 'confirmed') {
                    $order->confirmed_at = fake()->dateTimeBetween($order->submitted_at, 'now');
                }

                // Add 1-6 items per order
                $numItems = fake()->numberBetween(1, 6);
                $selectedProducts = $products->random(min($numItems, $products->count()));
                $subtotal = 0;

                foreach ($selectedProducts as $product) {
                    $quantity = fake()->numberBetween(1, 5);
                    $unitPrice = (float) $product->price;
                    $totalPrice = $unitPrice * $quantity;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'event_product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_category' => $product->category,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'total_price' => $totalPrice,
                    ]);

                    $subtotal += $totalPrice;
                }

                // Apply discount to some orders
                if (fake()->boolean(25)) {
                    $order->discount_type = fake()->randomElement(['percentage', 'fixed']);
                    $order->discount_value = $order->discount_type === 'percentage'
                        ? fake()->randomElement([5, 10, 15, 20])
                        : fake()->randomElement([500000, 1000000, 2000000]);
                }

                $order->subtotal = $subtotal;
                $order->recalculateTotal();
                $order->save();

                $orderCount++;
            }
        }

        return $orderCount;
    }
}
