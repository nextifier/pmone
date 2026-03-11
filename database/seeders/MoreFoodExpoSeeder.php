<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MoreFoodExpoSeeder extends Seeder
{
    /**
     * F&B brand data - variatif dan realistis.
     * [name, company_name, company_address, company_email, company_phone, description]
     */
    private array $brandData = [
        [
            'PT Boga Nusantara Jaya',
            'PT Boga Nusantara Jaya',
            'Jl. Industri Raya No. 45, Kawasan Industri Pulogadung, Jakarta Timur 13920',
            'info@boganusantara.co.id',
            '021-4612345',
            'Produsen makanan olahan tradisional Indonesia dengan teknologi modern. Spesialisasi rendang kemasan, sambal, dan bumbu masak siap pakai.',
        ],
        [
            'Kopi Archipelago',
            'PT Archipelago Coffee Indonesia',
            'Jl. Kemang Raya No. 12A, Kemang, Jakarta Selatan 12730',
            'hello@kopiarchipelago.id',
            '021-7195678',
            'Specialty coffee roaster yang mengangkat single-origin kopi dari seluruh nusantara. Tersedia di lebih dari 50 gerai.',
        ],
        [
            'Dapur Mama Ina',
            'CV Dapur Mama Ina',
            'Jl. Raya Darmo No. 88, Surabaya 60241',
            'order@dapurmamainna.com',
            '031-5634567',
            'Produsen frozen food rumahan premium - mulai dari siomay, batagor, pempek, hingga dimsum handmade.',
        ],
        [
            'SehatKu Organik',
            'PT SehatKu Natural Indonesia',
            'Jl. Raya Cisarua No. 15, Bogor 16750',
            'cs@sehatku-organik.co.id',
            '0251-8234567',
            'Pionir produk organik Indonesia. Menyediakan beras organik, madu hutan, granola, dan superfood lokal.',
        ],
        [
            'NusaBakery',
            'PT Nusa Bakery Indonesia',
            'Kawasan Industri Jababeka Blok V No. 23, Cikarang, Bekasi 17530',
            'sales@nusabakery.co.id',
            '021-89837654',
            'Pabrik roti dan pastry terbesar di Jawa Barat. Distribusi ke lebih dari 2.000 toko di seluruh Indonesia.',
        ],
        [
            'Sari Buah Tropika',
            'PT Sari Buah Tropika',
            'Jl. Raya Serang Km 14, Tangerang 15134',
            'info@saribuahtropika.com',
            '021-5951234',
            'Produsen jus buah segar dan konsentrat dari buah-buahan tropis Indonesia tanpa pengawet.',
        ],
        [
            'Ayam Bakar Pak Joko',
            'PT Joko Food Indonesia',
            'Jl. Pandanaran No. 67, Semarang 50134',
            'franchise@ayambakarpakjoko.com',
            '024-3548765',
            'Franchise ayam bakar dengan 120+ outlet di Indonesia. Resep turun-temurun dari Solo.',
        ],
        [
            'Indo Spice Trading',
            'PT Indo Spice Trading',
            'Jl. Pelabuhan II No. 5, Tanjung Priok, Jakarta Utara 14310',
            'export@indospice.co.id',
            '021-4353456',
            'Eksportir rempah-rempah premium Indonesia - lada, pala, cengkeh, kayu manis, dan vanili.',
        ],
        [
            'MieCap Tiga Rasa',
            'PT Tiga Rasa Food',
            'Jl. Raya Narogong Km 8, Bekasi 17116',
            'info@tigarasafood.com',
            '021-82412345',
            'Produsen mie instan premium dengan varian rasa lokal. Dikenal dengan MieCap seri Nusantara.',
        ],
        [
            'Gelato di Casa',
            'PT Casa Gelato Indonesia',
            'Jl. Senopati No. 40, Kebayoran Baru, Jakarta Selatan 12110',
            'info@gelatodicasa.id',
            '021-72567890',
            'Artisan gelato dengan bahan-bahan lokal berkualitas. 30+ rasa unik termasuk klepon, es teler, dan durian Musang King.',
        ],
        [
            'Kerupuk Nusantara',
            'CV Kerupuk Nusantara',
            'Jl. Raya Sidoarjo No. 34, Sidoarjo 61211',
            'order@kerupuknusantara.com',
            '031-8941234',
            'Produsen kerupuk tradisional khas Jawa Timur - kerupuk udang, ikan, dan sayur dalam kemasan modern.',
        ],
        [
            'PT Sambal Nusantara',
            'PT Sambal Nusantara',
            'Jl. Soekarno-Hatta No. 55, Bandung 40223',
            'info@sambalnusantara.id',
            '022-6034567',
            'Produsen sambal botolan dengan 25 varian dari berbagai daerah. Dari sambal matah Bali sampai rica-rica Manado.',
        ],
        [
            'Fresh Ocean Seafood',
            'PT Fresh Ocean Indonesia',
            'Jl. Raya Muara Baru No. 12, Penjaringan, Jakarta Utara 14440',
            'sales@freshocean.co.id',
            '021-6603456',
            'Supplier seafood segar dan frozen premium. Menyuplai ke hotel, restoran, dan katering di Jabodetabek.',
        ],
        [
            'Teh Nusantara Premium',
            'PT Teh Nusantara Premium',
            'Jl. Raya Puncak No. 88, Cisarua, Bogor 16750',
            'info@tehnusantara.co.id',
            '0251-8256789',
            'Produsen teh premium dari perkebunan sendiri di dataran tinggi Jawa Barat. Varian hijau, oolong, dan hitam.',
        ],
        [
            'Warung Digital Indonesia',
            'PT Warung Digital Indonesia',
            'Jl. HR Rasuna Said Kav. C-22, Jakarta Selatan 12940',
            'partner@warungdigital.id',
            '021-2528765',
            'Platform F&B digital - cloud kitchen, online ordering, dan sistem manajemen restoran terintegrasi.',
        ],
        [
            'Cokelat Ndalem',
            'PT Cokelat Ndalem Indonesia',
            'Jl. Tirtodipuran No. 18, Yogyakarta 55143',
            'hello@cokelatndalem.com',
            '0274-387654',
            'Bean-to-bar chocolate craft dari biji kakao pilihan Indonesia. Produk handmade dengan sentuhan budaya Jawa.',
        ],
        [
            'PT Sumber Protein Lestari',
            'PT Sumber Protein Lestari',
            'Kawasan Industri MM2100 Blok DD No. 7, Cibitung, Bekasi 17520',
            'info@sumberprotein.co.id',
            '021-89981234',
            'Produsen plant-based protein dan meat alternative pertama di Indonesia. Tempeh-based dan soy-based products.',
        ],
        [
            'Raja Durian Nusantara',
            'CV Raja Durian Nusantara',
            'Jl. Jend. Sudirman No. 99, Medan 20217',
            'order@rajadurian.id',
            '061-4517654',
            'Spesialis produk olahan durian premium - pancake durian, dodol, es krim, dan durian frozen utuh.',
        ],
        [
            'Healthy Bowl Co.',
            'PT Healthy Bowl Indonesia',
            'Jl. Gunawarman No. 25, Kebayoran Baru, Jakarta Selatan 12110',
            'hi@healthybowl.co.id',
            '021-7250987',
            'Brand healthy food bowl - acai bowl, smoothie bowl, poke bowl, dan salad bowl untuk gaya hidup sehat urban.',
        ],
        [
            'Keju Kraft Nusantara',
            'PT Keju Kraft Nusantara',
            'Jl. Raya Bandung-Garut Km 25, Sumedang 45363',
            'sales@kejukraft.co.id',
            '0261-2034567',
            'Produsen keju artisan lokal - mozzarella, ricotta, dan keju olahan dari susu sapi perah lokal.',
        ],
    ];

    public function run(): void
    {
        $this->command->info('Seeding MoreFood Expo Indonesia data...');

        $creator = User::role('master')->first();

        if (! $creator) {
            $this->command->error('No master user found. Aborting.');

            return;
        }

        $event = Event::where('slug', 'morefood-expo-indonesia')->first();

        if (! $event) {
            $this->command->error('Event "MoreFood Expo Indonesia" not found. Aborting.');

            return;
        }

        $salesUsers = User::role(['staff', 'admin'])->pluck('id')->toArray();
        $sourcePdf = $_SERVER['HOME'].'/Downloads/test.pdf';

        $hasPdf = file_exists($sourcePdf);

        if (! $hasPdf) {
            $this->command->warn('test.pdf not found in ~/Downloads/. Skipping document submissions.');
        }

        // 1. Create brands
        $this->command->info('Creating F&B brands...');
        $brands = $this->createBrands($creator);
        $this->command->info('Created '.count($brands).' brands.');

        // 2. Assign brands as exhibitors
        $this->command->info('Assigning exhibitors to event...');
        $brandEvents = $this->assignExhibitors($event, $brands, $salesUsers, $creator);
        $this->command->info('Assigned '.$brandEvents->count().' exhibitors.');

        // 3. Upload documents for some exhibitors
        if ($hasPdf) {
            $this->command->info('Creating document submissions...');
            $docCount = $this->createDocumentSubmissions($event, $brandEvents, $sourcePdf, $creator);
            $this->command->info("Created {$docCount} document submissions.");
        }

        // 4. Create orders
        $this->command->info('Creating orders...');
        $products = EventProduct::where('event_id', $event->id)->where('is_active', true)->get();
        $orderCount = $this->createOrders($brandEvents, $products, $creator);
        $this->command->info("Created {$orderCount} orders.");

        $this->command->info('MoreFood Expo Indonesia seeding complete!');
    }

    private function createBrands(User $creator): \Illuminate\Support\Collection
    {
        $brands = collect();

        foreach ($this->brandData as $data) {
            [$name, $companyName, $address, $email, $phone, $description] = $data;

            $slug = Str::slug($name);

            // Skip if brand already exists
            $existing = Brand::where('slug', $slug)->first();
            if ($existing) {
                $brands->push($existing);
                $this->command->line("  - Skipped (exists): {$name}");

                continue;
            }

            $brand = Brand::create([
                'name' => $name,
                'description' => $description,
                'company_name' => $companyName,
                'company_address' => $address,
                'company_email' => $email,
                'company_phone' => $phone,
                'status' => 'active',
                'visibility' => fake()->randomElement(['public', 'private', 'private']),
                'created_by' => $creator->id,
            ]);

            $brands->push($brand);
            $this->command->line("  + Created: {$name}");
        }

        return $brands;
    }

    private function assignExhibitors(Event $event, \Illuminate\Support\Collection $brands, array $salesUsers, User $creator): \Illuminate\Support\Collection
    {
        $brandEvents = collect();
        $boothCounter = 1;
        $halls = ['A', 'B', 'C'];

        foreach ($brands as $brand) {
            // Skip if already assigned to this event
            if (BrandEvent::where('brand_id', $brand->id)->where('event_id', $event->id)->exists()) {
                $existing = BrandEvent::where('brand_id', $brand->id)->where('event_id', $event->id)->first();
                $brandEvents->push($existing);
                $this->command->line("  - Skipped (exists): {$brand->name}");

                continue;
            }

            $boothType = fake()->randomElement(['raw_space', 'raw_space', 'standard_shell_scheme', 'enhanced_shell_scheme', 'table_chair_only']);
            $boothSize = match ($boothType) {
                'raw_space' => fake()->randomElement([18, 27, 36, 54, 72]),
                'standard_shell_scheme' => fake()->randomElement([9, 12, 15, 18]),
                'enhanced_shell_scheme' => fake()->randomElement([9, 12, 15, 18, 24]),
                'table_chair_only' => fake()->randomElement([4, 6, 9]),
            };
            $boothPrice = match ($boothType) {
                'raw_space' => $boothSize * fake()->numberBetween(1800000, 2800000),
                'standard_shell_scheme' => $boothSize * fake()->numberBetween(3000000, 4500000),
                'enhanced_shell_scheme' => $boothSize * fake()->numberBetween(4000000, 6000000),
                'table_chair_only' => $boothSize * fake()->numberBetween(2000000, 3000000),
            };

            $hall = $halls[array_rand($halls)];
            $boothNumber = sprintf('%s-%03d', $hall, $boothCounter);

            $status = fake()->randomElement(['confirmed', 'confirmed', 'confirmed', 'active', 'draft']);

            $brandEvent = BrandEvent::create([
                'brand_id' => $brand->id,
                'event_id' => $event->id,
                'booth_number' => $boothNumber,
                'booth_size' => $boothSize,
                'booth_price' => $boothPrice,
                'booth_type' => $boothType,
                'fascia_name' => fake()->boolean(70) ? $brand->name : null,
                'badge_name' => fake()->boolean(50) ? $brand->company_name : null,
                'sales_id' => ! empty($salesUsers) ? fake()->randomElement($salesUsers) : null,
                'status' => $status,
                'promotion_post_limit' => fake()->randomElement([1, 2, 3]),
                'notes' => fake()->optional(0.3)->sentence(),
            ]);

            $brandEvents->push($brandEvent);
            $boothCounter++;
        }

        return $brandEvents;
    }

    private function createDocumentSubmissions(Event $event, \Illuminate\Support\Collection $brandEvents, string $sourcePdf, User $creator): int
    {
        $documents = EventDocument::where('event_id', $event->id)
            ->where('document_type', 'file_upload')
            ->get();

        $checkboxDocs = EventDocument::where('event_id', $event->id)
            ->where('document_type', 'checkbox_agreement')
            ->get();

        $count = 0;

        foreach ($brandEvents as $brandEvent) {
            // ~60% of exhibitors submit documents
            if (fake()->boolean(60) === false) {
                continue;
            }

            $boothIdentifier = $brandEvent->booth_number;

            if (empty($boothIdentifier)) {
                continue;
            }

            // Submit checkbox agreements
            foreach ($checkboxDocs as $doc) {
                if (! $doc->appliesToBoothType($brandEvent->booth_type?->value)) {
                    continue;
                }

                $existing = EventDocumentSubmission::where('event_document_id', $doc->id)
                    ->where('booth_identifier', $boothIdentifier)
                    ->where('event_id', $event->id)
                    ->first();

                if ($existing) {
                    continue;
                }

                EventDocumentSubmission::create([
                    'event_document_id' => $doc->id,
                    'booth_identifier' => $boothIdentifier,
                    'event_id' => $event->id,
                    'agreed_at' => now()->subDays(fake()->numberBetween(1, 30)),
                    'document_version' => $doc->content_version,
                    'submitted_by' => $creator->id,
                    'submitted_at' => now()->subDays(fake()->numberBetween(1, 30)),
                ]);
                $count++;
            }

            // Submit file upload documents
            foreach ($documents as $doc) {
                if (! $doc->appliesToBoothType($brandEvent->booth_type?->value)) {
                    continue;
                }

                // ~70% chance to submit each applicable document
                if (fake()->boolean(70) === false) {
                    continue;
                }

                $existing = EventDocumentSubmission::where('event_document_id', $doc->id)
                    ->where('booth_identifier', $boothIdentifier)
                    ->where('event_id', $event->id)
                    ->first();

                if ($existing) {
                    continue;
                }

                $submission = EventDocumentSubmission::create([
                    'event_document_id' => $doc->id,
                    'booth_identifier' => $boothIdentifier,
                    'event_id' => $event->id,
                    'document_version' => $doc->content_version,
                    'submitted_by' => $creator->id,
                    'submitted_at' => now()->subDays(fake()->numberBetween(1, 30)),
                ]);

                // Copy and rename the PDF for this submission
                $brandName = Str::slug($brandEvent->brand->name);
                $docSlug = Str::slug($doc->title);
                $fileName = "{$brandName}_{$docSlug}_{$boothIdentifier}.pdf";

                $tempPath = sys_get_temp_dir().'/'.$fileName;
                copy($sourcePdf, $tempPath);

                $submission->addMedia($tempPath)
                    ->usingFileName($fileName)
                    ->toMediaCollection('submission_file');

                $count++;
            }
        }

        return $count;
    }

    private function createOrders(\Illuminate\Support\Collection $brandEvents, \Illuminate\Support\Collection $products, User $creator): int
    {
        $orderCount = 0;

        if ($products->isEmpty()) {
            $this->command->warn('No products found for event. Skipping orders.');

            return 0;
        }

        foreach ($brandEvents as $brandEvent) {
            // ~55% of exhibitors place orders
            if (fake()->boolean(55) === false) {
                continue;
            }

            // 1-2 orders per exhibitor
            $numOrders = fake()->numberBetween(1, 2);

            for ($i = 0; $i < $numOrders; $i++) {
                $operationalStatus = fake()->randomElement([
                    'submitted', 'submitted', 'submitted',
                    'confirmed', 'confirmed',
                    'processing',
                    'completed',
                    'cancelled',
                ]);

                $order = Order::create([
                    'brand_event_id' => $brandEvent->id,
                    'operational_status' => $operationalStatus,
                    'payment_status' => match ($operationalStatus) {
                        'confirmed' => fake()->randomElement(['not_invoiced', 'invoiced']),
                        'processing' => fake()->randomElement(['invoiced', 'paid']),
                        'completed' => 'paid',
                        'cancelled' => 'not_invoiced',
                        default => 'not_invoiced',
                    },
                    'notes' => fake()->optional(0.3)->randomElement([
                        'Mohon dikirim sebelum hari H',
                        'Tolong pasang di area depan booth',
                        'Butuh instalasi pagi hari sebelum buka',
                        'Konfirmasi dulu sebelum dipasang',
                        'Tambahan dari order sebelumnya',
                        'Request khusus - posisi dekat pintu masuk',
                    ]),
                    'subtotal' => 0,
                    'tax_rate' => 11.00,
                    'tax_amount' => 0,
                    'total' => 0,
                    'submitted_at' => fake()->dateTimeBetween('-2 months', 'now'),
                    'confirmed_at' => null,
                    'created_by' => $creator->id,
                ]);

                if (in_array($operationalStatus, ['confirmed', 'processing', 'completed'])) {
                    $order->confirmed_at = fake()->dateTimeBetween($order->submitted_at, 'now');
                }

                // Add 1-5 items per order
                $numItems = fake()->numberBetween(1, 5);
                $selectedProducts = $products->random(min($numItems, $products->count()));
                $subtotal = 0;

                foreach ($selectedProducts as $product) {
                    $quantity = fake()->numberBetween(1, 4);
                    $unitPrice = (float) $product->price;
                    $totalPrice = $unitPrice * $quantity;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'event_product_id' => $product->id,
                        'product_name' => $product->name,
                        'category_id' => $product->category_id,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'total_price' => $totalPrice,
                    ]);

                    $subtotal += $totalPrice;
                }

                // Apply discount to some orders (~20%)
                if (fake()->boolean(20)) {
                    $order->discount_type = fake()->randomElement(['percentage', 'fixed']);
                    $order->discount_value = $order->discount_type === 'percentage'
                        ? fake()->randomElement([5, 10, 15, 20])
                        : fake()->randomElement([500000, 1000000, 2000000, 5000000]);
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
