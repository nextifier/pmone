<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShowreelDataSeeder extends Seeder
{
    /**
     * Product catalog templates (same as EventExhibitorSeeder).
     */
    private array $productTemplates = [
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
    ];

    /**
     * Extra brand names to expand the pool if needed.
     */
    private array $extraBrandNames = [
        'PT Astra International', 'PT United Tractors', 'PT Indofood Sukses Makmur',
        'PT Telkom Indonesia', 'PT Bank Central Asia', 'PT Gudang Garam',
        'PT Unilever Indonesia', 'PT HM Sampoerna', 'PT Charoen Pokphand Indonesia',
        'PT Kalbe Farma', 'PT Indocement Tunggal Prakarsa', 'PT Sinar Mas Agro',
        'PT XL Axiata', 'PT Indosat Ooredoo', 'PT Tower Bersama Infrastructure',
        'PT Matahari Department Store', 'PT Erajaya Swasembada', 'PT Map Aktif Adiperkasa',
        'PT Japfa Comfeed Indonesia', 'PT Malindo Feedmill', 'PT Sierad Produce',
        'PT Krakatau Steel', 'PT Tembaga Mulia Semanan', 'PT Betonjaya Manunggal',
        'PT Kedawung Setia Industrial', 'PT Langgeng Makmur Industri', 'PT Lion Metal Works',
        'PT Martina Berto', 'PT Mustika Ratu', 'PT Mandom Indonesia',
        'PT Tempo Scan Pacific', 'PT Darya-Varia Laboratoria', 'PT Kimia Farma',
        'PT Bio Farma', 'PT Phapros', 'PT Pyridam Farma',
        'PT Blue Bird', 'PT Express Transindo Utama', 'PT Garuda Indonesia',
        'PT Sriwijaya Air', 'PT Citilink Indonesia', 'PT Lion Mentari Airlines',
        'PT Bumi Serpong Damai', 'PT Alam Sutera Realty', 'PT Agung Podomoro Land',
        'PT Intiland Development', 'PT Duta Pertiwi', 'PT Metropolitan Land',
        'PT Jaya Real Property', 'PT Mega Manunggal Property', 'PT Hanson International',
        'PT Soechi Lines', 'PT Wintermar Offshore Marine', 'PT Pelita Samudera Shipping',
        'PT Adi Sarana Armada', 'PT Mitra Pinasthika Mustika', 'PT Tunas Ridean',
        'PT Selamat Sempurna', 'PT Astra Otoparts', 'PT Indo Kordsa',
        'PT Supreme Cable Manufacturing', 'PT Kabelindo Murni', 'PT Voksel Electric',
        'PT Sat Nusapersada', 'PT Hartadinata Abadi', 'PT Sunson Textile Manufacturer',
        'PT Pan Brothers', 'PT Sri Rejeki Isman', 'PT Asia Pacific Fibers',
        'PT Polychem Indonesia', 'PT Indo Acidatama', 'PT Barito Pacific',
        'PT Chandra Asri Petrochemical', 'PT Lotte Chemical Titan', 'PT Trias Sentosa',
        'PT Impack Pratama Industri', 'PT Champion Pacific Indonesia', 'PT Panca Budi Idaman',
        'CV Harmoni Kreasi', 'CV Sinar Jaya Abadi', 'CV Mitra Usaha Mandiri',
        'CV Putra Nusantara', 'CV Bintang Timur', 'CV Cakrawala Indah',
        'PT Nusantara Sejahtera Raya', 'PT Karya Mandiri Utama', 'PT Sukses Bersama Indonesia',
        'PT Cahaya Nusantara Gemilang', 'PT Bumi Perkasa Sentosa', 'PT Mitra Global Teknik',
        'PT Inovasi Digital Nusantara', 'PT Kreasi Anak Bangsa', 'PT Solusi Prima Indonesia',
        'PT Graha Teknologi', 'PT Dinamika Cipta Mandiri', 'PT Omega Maju Bersama',
        'PT Sentral Karya Abadi', 'PT Prima Energi Indonesia', 'PT Wahana Cipta Sejahtera',
        'PT Nusa Karya Mandiri', 'PT Aneka Tambang', 'PT Bukit Asam',
    ];

    public function run(): void
    {
        $this->command->info('Seeding showreel data...');

        $creator = User::role('master')->first();

        if (! $creator) {
            $this->command->error('No master user found. Aborting.');

            return;
        }

        // Step 1: Ensure enough brands in pool
        $brandPool = $this->ensureBrandPool($creator);
        $this->command->info("Brand pool: {$brandPool->count()} brands available.");

        // Step 2: Process each event
        $events = Event::with('project')->get();

        foreach ($events as $event) {
            $this->command->info("Processing: {$event->title}...");

            // Ensure event has products
            $products = $this->ensureEventProducts($event, $creator);
            $this->command->line("  Products: {$products->count()}");

            // Top up exhibitors to target
            $currentCount = $event->brandEvents()->count();
            $target = fake()->numberBetween(120, 180);

            if ($currentCount >= 100) {
                $this->command->line("  Exhibitors: {$currentCount} (already sufficient, skipping)");
            } else {
                $needed = $target - $currentCount;
                $existingBrandIds = $event->brandEvents()->pluck('brand_id')->toArray();
                $availableBrands = $brandPool->whereNotIn('id', $existingBrandIds)->shuffle()->take($needed);

                $newBrandEvents = $this->assignExhibitors($event, $availableBrands, $creator, $currentCount);
                $this->command->line("  Exhibitors: {$currentCount} + {$newBrandEvents->count()} = ".($currentCount + $newBrandEvents->count()));
            }

            // Create orders for 75% of brand_events that don't have orders yet
            $orderCount = $this->createOrdersForEvent($event, $products, $creator);
            $this->command->line("  New orders: {$orderCount}");
        }

        $this->command->info('Showreel data seeding complete!');
    }

    private function ensureBrandPool(User $creator): Collection
    {
        $existingSlugs = Brand::pluck('slug')->toArray();
        $created = 0;

        foreach ($this->extraBrandNames as $name) {
            $slug = Str::slug($name);

            if (in_array($slug, $existingSlugs)) {
                continue;
            }

            Brand::create([
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

            $existingSlugs[] = $slug;
            $created++;
        }

        if ($created > 0) {
            $this->command->line("  Created {$created} new brands.");
        }

        return Brand::where('status', 'active')->get();
    }

    private function ensureEventProducts(Event $event, User $creator): Collection
    {
        $existingProducts = EventProduct::where('event_id', $event->id)->get();

        if ($existingProducts->count() >= 10) {
            return $existingProducts;
        }

        $products = collect();

        foreach ($this->productTemplates as $index => [$categoryTitle, $name, $price, $unit]) {
            // Check if product already exists
            $existing = $existingProducts->firstWhere('name', $name);
            if ($existing) {
                $products->push($existing);

                continue;
            }

            $adjustedPrice = $price * fake()->randomFloat(2, 0.8, 1.2);

            $categoryModel = EventProductCategory::firstOrCreate(
                ['event_id' => $event->id, 'title' => $categoryTitle],
                ['slug' => Str::slug($categoryTitle)],
            );

            $product = EventProduct::create([
                'event_id' => $event->id,
                'category_id' => $categoryModel->id,
                'name' => $name,
                'price' => round($adjustedPrice, -3),
                'unit' => $unit,
                'is_active' => true,
                'order_column' => $index + 1,
                'created_by' => $creator->id,
            ]);

            $products->push($product);
        }

        return $products->merge($existingProducts)->unique('id');
    }

    private function assignExhibitors(Event $event, Collection $brands, User $creator, int $startCounter): Collection
    {
        $salesUsers = User::role(['staff', 'admin'])->pluck('id')->toArray();
        $brandEvents = collect();
        $boothCounter = $startCounter + 1;

        foreach ($brands as $brand) {
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

    private function createOrdersForEvent(Event $event, Collection $products, User $creator): int
    {
        if ($products->isEmpty()) {
            return 0;
        }

        // Get all brand_events for this event that don't have orders yet
        $brandEventsWithoutOrders = BrandEvent::where('event_id', $event->id)
            ->whereDoesntHave('orders')
            ->get();

        // Also get brand_events WITH orders (we'll skip those)
        $brandEventsWithOrders = BrandEvent::where('event_id', $event->id)
            ->whereHas('orders')
            ->count();

        $totalBrandEvents = $brandEventsWithoutOrders->count() + $brandEventsWithOrders;

        // Target: 75% of ALL brand_events should have orders
        $targetWithOrders = (int) ceil($totalBrandEvents * 0.75);
        $needOrders = max(0, $targetWithOrders - $brandEventsWithOrders);

        // Select that many from the ones without orders
        $selectedForOrders = $brandEventsWithoutOrders->shuffle()->take($needOrders);

        $orderCount = 0;

        foreach ($selectedForOrders as $brandEvent) {
            $numOrders = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numOrders; $i++) {
                $operationalStatus = fake()->randomElement([
                    'submitted', 'submitted', 'submitted',       // 30%
                    'confirmed', 'confirmed', 'confirmed', 'confirmed', // 40%
                    'processing', 'processing',                   // 15% (approx)
                    'completed',                                  // 10%
                    'cancelled',                                  // 5% (approx)
                ]);

                $paymentStatus = match ($operationalStatus) {
                    'cancelled' => 'not_invoiced',
                    'submitted' => fake()->randomElement(['not_invoiced', 'not_invoiced', 'invoiced']),
                    default => fake()->randomElement([
                        'not_invoiced', 'not_invoiced',
                        'invoiced', 'invoiced', 'invoiced',
                        'paid', 'paid',
                    ]),
                };

                $submittedAt = fake()->dateTimeBetween('-6 months', 'now');

                $order = Order::create([
                    'brand_event_id' => $brandEvent->id,
                    'operational_status' => $operationalStatus,
                    'payment_status' => $paymentStatus,
                    'notes' => fake()->optional(0.2)->sentence(),
                    'subtotal' => 0,
                    'tax_rate' => 11.00,
                    'tax_amount' => 0,
                    'total' => 0,
                    'submitted_at' => $submittedAt,
                    'confirmed_at' => in_array($operationalStatus, ['confirmed', 'processing', 'completed'])
                        ? fake()->dateTimeBetween($submittedAt, 'now')
                        : null,
                    'created_by' => $creator->id,
                ]);

                // Add 1-6 items
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
                        'category_id' => $product->category_id,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'total_price' => $totalPrice,
                    ]);

                    $subtotal += $totalPrice;
                }

                $discountAmount = 0;
                if (fake()->boolean(25)) {
                    $discountAmount = fake()->randomElement([
                        round($subtotal * 0.05, 2),
                        round($subtotal * 0.10, 2),
                        round($subtotal * 0.15, 2),
                        500000,
                        1000000,
                    ]);
                    $discountAmount = min($discountAmount, $subtotal);
                }

                $taxableAmount = $subtotal - $discountAmount;
                $taxAmount = round($taxableAmount * (float) $order->tax_rate / 100, 2);

                $order->subtotal = $subtotal;
                $order->discount_amount = $discountAmount;
                $order->tax_amount = $taxAmount;
                $order->total = $taxableAmount + $taxAmount;
                $order->save();

                $orderCount++;
            }
        }

        return $orderCount;
    }
}
