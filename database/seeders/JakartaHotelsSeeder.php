<?php

namespace Database\Seeders;

use App\Enums\TransferDirection;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * Seeds curated Jakarta-area hotels grouped by proximity to major MICE venues:
 *   - JCC Senayan (Senayan, Jakarta Pusat)
 *   - JIEXPO Kemayoran (Kemayoran, Jakarta Pusat)
 *   - ICE BSD (BSD City, Tangerang)
 *   - NICE PIK 2 (Pantai Indah Kapuk 2, Jakarta Utara)
 *
 * Hotels are populated as GLOBAL master records (no event attachment).
 * Staff attaches them per event via the master/event UI.
 *
 * Photos use Unsplash Source curated hotel/room collections as stable URLs.
 * Each call to https://source.unsplash.com/<id>/<size> returns the same image.
 */
class JakartaHotelsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->dataset() as $payload) {
            $rooms = $payload['rooms'];
            $transferPrice = $payload['_transfer_price'] ?? 350_000;
            unset($payload['rooms'], $payload['hero'], $payload['gallery'], $payload['_transfer_price']);

            /** @var Hotel $hotel */
            $hotel = Hotel::firstOrCreate(
                ['slug' => $payload['slug']],
                $payload,
            );

            foreach ($rooms as $room) {
                RoomType::firstOrCreate(
                    ['hotel_id' => $hotel->id, 'name' => $room['name']],
                    array_merge($room, ['is_active' => true]),
                );
            }

            // Generic Transfer In + Transfer Out (no vehicle type specified)
            // Generic "Transfer In/Out" - no vehicle type, no fixed pax cap.
            HotelTransferOption::firstOrCreate(
                ['hotel_id' => $hotel->id, 'label' => 'Transfer In'],
                [
                    'direction' => TransferDirection::In,
                    'price' => $transferPrice,
                    'max_pax' => null,
                    'is_active' => true,
                ],
            );
            HotelTransferOption::firstOrCreate(
                ['hotel_id' => $hotel->id, 'label' => 'Transfer Out'],
                [
                    'direction' => TransferDirection::Out,
                    'price' => $transferPrice,
                    'max_pax' => null,
                    'is_active' => true,
                ],
            );

            $this->command?->info("Seeded hotel: {$hotel->name}");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dataset(): array
    {
        return array_merge(
            $this->jccSenayan(),
            $this->jiexpoKemayoran(),
            $this->iceBsd(),
            $this->nicePik2(),
        );
    }

    /**
     * Near JCC Senayan - Jl. Gatot Subroto, Senayan, Jakarta Pusat.
     */
    private function jccSenayan(): array
    {
        return [
            [
                'slug' => 'fairmont-jakarta',
                'name' => 'Fairmont Jakarta',
                'description' => 'Luxury 5-star hotel adjacent to Plaza Senayan and within 1 km of JCC Senayan. Features expansive rooms with city views, multi-restaurant dining (1945, Spectrum, View), Willow Stream Spa, and direct access to Sentral Senayan.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Jl. Asia Afrika No.8, Gelora, Tanah Abang',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/KrwQVdJynVqXhUuB9',
                'contact_email' => 'jakarta@fairmont.com',
                'contact_phone' => '+62 21 2970 3333',
                'cancellation_policy' => 'Free cancellation up to 72 hours before check-in. After that, the first night will be charged.',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 10.00,
                'is_active' => true,
                '_transfer_price' => 450_000,
                'rooms' => [
                    [
                        'name' => 'Fairmont Room', 'slug' => 'fairmont-room',
                        'description' => 'Spacious 50 sqm room with floor-to-ceiling windows, king or twin bed, marble bathroom with rain shower, and Fairmont signature bed.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 50.00, 'base_rate' => 2_800_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Signature Room', 'slug' => 'signature-room',
                        'description' => 'Upgraded 55 sqm room on higher floors with skyline views, premium amenities, and complimentary minibar refresh.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 55.00, 'base_rate' => 3_400_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Fairmont Suite', 'slug' => 'fairmont-suite',
                        'description' => '90 sqm suite with separate living area, walk-in closet, executive lounge access including breakfast, evening cocktails, and dedicated check-in.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 90.00, 'base_rate' => 5_500_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'hotel-mulia-senayan',
                'name' => 'Hotel Mulia Senayan',
                'description' => 'Iconic 5-star hotel facing GBK Senayan sports complex, 800 m from JCC. Known for its grand lobby, eight on-site restaurants including Edogin and Table8, and one of the largest standard rooms in Jakarta at 70 sqm.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Jl. Asia Afrika, Senayan',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/8sRGNXKVgKRcVf3F8',
                'contact_email' => 'info@themulia.com',
                'contact_phone' => '+62 21 574 7777',
                'cancellation_policy' => 'Free cancellation up to 48 hours before check-in. Late cancellation charged one night room rate.',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 10.00,
                'is_active' => true,
                '_transfer_price' => 450_000,
                'rooms' => [
                    [
                        'name' => 'Grandeur', 'slug' => 'grandeur',
                        'description' => 'Spacious 70 sqm rooms with marble bathroom, walk-in closet, and large work desk. King or twin bed configurations available.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 70.00, 'base_rate' => 2_900_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Mulia Executive', 'slug' => 'mulia-executive',
                        'description' => 'Executive room with The Lounge access (breakfast, afternoon tea, evening cocktails) and city views from higher floors.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 70.00, 'base_rate' => 3_600_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Mulia Suite', 'slug' => 'mulia-suite',
                        'description' => '110 sqm suite with separate living and dining area, two TVs, walk-in closet, and panoramic windows.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 110.00, 'base_rate' => 6_200_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'the-sultan-hotel-residence-jakarta',
                'name' => 'The Sultan Hotel & Residence Jakarta',
                'description' => '5-star landmark hotel directly across from JCC Senayan with 695 rooms set in 13 hectares of tropical gardens. Walking distance to Senayan venues and Plaza Senayan.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Jl. Gatot Subroto No.Kav. 10, Gelora, Tanah Abang',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/JJSwSL5fmwYi8X4S7',
                'contact_email' => 'reservation@sultanjakarta.com',
                'contact_phone' => '+62 21 570 3600',
                'cancellation_policy' => 'Free cancellation up to 24 hours before check-in.',
                'commission_rate' => 10.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 400_000,
                'rooms' => [
                    [
                        'name' => 'Deluxe Garden View', 'slug' => 'deluxe-garden-view',
                        'description' => '38 sqm room overlooking the tropical garden and swimming pool with king or twin bed and ergonomic work desk.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 38.00, 'base_rate' => 1_400_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Premier City View', 'slug' => 'premier-city-view',
                        'description' => 'Higher-floor room with city skyline view, complimentary high-speed WiFi, and premium toiletries.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 38.00, 'base_rate' => 1_700_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Executive Suite', 'slug' => 'executive-suite-sultan',
                        'description' => '76 sqm suite with separate living room, Executive Club lounge access, and personalized check-in.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 76.00, 'base_rate' => 3_500_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'shangri-la-hotel-jakarta',
                'name' => 'Shangri-La Hotel Jakarta',
                'description' => '5-star hotel inside the Kota BNI complex, ~2 km from JCC. Renowned for its breakfast spread at SATOO, BLU martini bar, and a 35-meter outdoor pool.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Kota BNI, Jl. Jend. Sudirman Kav. 1, Karet Tengsin',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/L8gMmtkAVwsuvg6V8',
                'contact_email' => 'slj@shangri-la.com',
                'contact_phone' => '+62 21 2922 9999',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 10.00,
                'is_active' => true,
                '_transfer_price' => 425_000,
                'rooms' => [
                    [
                        'name' => 'Deluxe Room', 'slug' => 'deluxe-room-sl',
                        'description' => '42 sqm room with Sudirman view, king or twin bed, and Shangri-La signature bedding.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 42.00, 'base_rate' => 2_600_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Horizon Club Room', 'slug' => 'horizon-club-room',
                        'description' => '42 sqm room with access to Horizon Club Lounge (breakfast, all-day refreshments, evening canapés, private check-in).',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 42.00, 'base_rate' => 3_900_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Specialty Suite', 'slug' => 'specialty-suite',
                        'description' => '84 sqm suite with separate living area, walk-in closet, and signature bathroom amenities.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 84.00, 'base_rate' => 6_100_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'the-ritz-carlton-pacific-place',
                'name' => 'The Ritz-Carlton Jakarta, Pacific Place',
                'description' => '5-star luxury hotel atop Pacific Place mall, 3 km from JCC. Features Asaya wellness sanctuary, Pacific restaurant for international dining, and direct mall access via private elevator.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Jl. Jenderal Sudirman Kav. 52-53, SCBD',
                    'city' => 'Kota Jakarta Selatan',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/c8RY2EYJqJV7mwSm9',
                'contact_email' => 'reservations.rcjk@ritzcarlton.com',
                'contact_phone' => '+62 21 2550 1888',
                'cancellation_policy' => 'Free cancellation up to 72 hours before arrival.',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 10.00,
                'is_active' => true,
                '_transfer_price' => 500_000,
                'rooms' => [
                    [
                        'name' => 'Grand Room', 'slug' => 'grand-room',
                        'description' => '49 sqm room with floor-to-ceiling windows, marble bathroom with separate rain shower and tub, and signature Ritz-Carlton bedding.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 49.00, 'base_rate' => 3_800_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Club Room', 'slug' => 'club-room-rc',
                        'description' => '49 sqm room with Club Lounge access including five culinary presentations a day and dedicated concierge.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 49.00, 'base_rate' => 5_200_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Executive Suite', 'slug' => 'executive-suite-rc',
                        'description' => '100 sqm suite with separate living and dining area, executive desk, and panoramic SCBD views.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 100.00, 'base_rate' => 8_500_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Near JIEXPO Kemayoran.
     */
    private function jiexpoKemayoran(): array
    {
        return [
            [
                'slug' => 'holiday-inn-express-jakarta-international-expo',
                'name' => 'Holiday Inn Express Jakarta International Expo',
                'description' => '3-star hotel attached to JIEXPO Kemayoran. The closest accommodation to the exhibition halls with covered walkway access to the venue. Includes complimentary Express Start breakfast and high-speed WiFi.',
                'star_rating' => 3,
                'address' => [
                    'street' => 'Jl. Benyamin Sueb Kav.D6, Pademangan Timur, Kemayoran',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/qHL3p1JjFsCEpEXX9',
                'contact_email' => 'reservation.hiejiakarta@ihg.com',
                'contact_phone' => '+62 21 2664 5555',
                'cancellation_policy' => 'Free cancellation up to 24 hours before arrival.',
                'commission_rate' => 10.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 5.00,
                'is_active' => true,
                '_transfer_price' => 300_000,
                'rooms' => [
                    [
                        'name' => 'Standard Queen', 'slug' => 'standard-queen-hie',
                        'description' => '22 sqm efficient room with queen bed, walk-in shower, work desk, and 43-inch smart TV.',
                        'max_pax' => 2, 'bed_type' => 'Queen', 'area_sqm' => 22.00, 'base_rate' => 850_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Standard Twin', 'slug' => 'standard-twin-hie',
                        'description' => '22 sqm room with two single beds, ideal for business travelers sharing rooms.',
                        'max_pax' => 2, 'bed_type' => 'Twin', 'area_sqm' => 22.00, 'base_rate' => 850_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'b-hotel-jakarta',
                'name' => 'b Hotel Jakarta',
                'description' => '4-star hotel inside JIEXPO Kemayoran complex with walking access to all exhibition halls. Lobby cafe, all-day dining at Cabbages & Condoms, and direct shuttle to exhibition events.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Benyamin Sueb Blok D6, JIEXPO Kemayoran',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/JCXKzPbNAYjC4Jij9',
                'contact_email' => 'info@bhoteljakarta.com',
                'contact_phone' => '+62 21 2664 8888',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 10.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 5.00,
                'is_active' => true,
                '_transfer_price' => 300_000,
                'rooms' => [
                    [
                        'name' => 'Superior King', 'slug' => 'superior-king-bhotel',
                        'description' => '28 sqm room with king bed, rain shower, work desk, and views of the JIEXPO complex.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 28.00, 'base_rate' => 1_100_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Deluxe Twin', 'slug' => 'deluxe-twin-bhotel',
                        'description' => '32 sqm room with two queen beds, ideal for groups of two adults.',
                        'max_pax' => 2, 'bed_type' => 'Twin Queen', 'area_sqm' => 32.00, 'base_rate' => 1_250_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Family Suite', 'slug' => 'family-suite-bhotel',
                        'description' => '50 sqm suite with two bedrooms and a small living area, suitable for families.',
                        'max_pax' => 4, 'bed_type' => 'King + Twin', 'area_sqm' => 50.00, 'base_rate' => 2_200_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'aston-inn-kemayoran',
                'name' => 'ASTON Inn Kemayoran',
                'description' => '3-star hotel approximately 2 km from JIEXPO with airport shuttle service, rooftop pool, and Sky Lounge restaurant. Popular pick for budget-conscious exhibition visitors.',
                'star_rating' => 3,
                'address' => [
                    'street' => 'Jl. Garuda Raya No.10, Kemayoran',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/oZN52KMpgvSE5xJZ8',
                'contact_email' => 'reservation@astoninnkemayoran.com',
                'contact_phone' => '+62 21 2208 6868',
                'cancellation_policy' => 'Free cancellation up to 24 hours before arrival.',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 5.00,
                'is_active' => true,
                '_transfer_price' => 250_000,
                'rooms' => [
                    [
                        'name' => 'Superior Room', 'slug' => 'superior-room-aston',
                        'description' => '24 sqm room with king bed, hot shower, work desk, and complimentary high-speed WiFi.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 24.00, 'base_rate' => 650_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Deluxe Room', 'slug' => 'deluxe-room-aston',
                        'description' => '28 sqm upgraded room on higher floor with city view and bathtub.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 28.00, 'base_rate' => 800_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'harris-hotel-kelapa-gading',
                'name' => 'HARRIS Hotel Kelapa Gading',
                'description' => '4-star vibrant hotel ~5 km from JIEXPO, connected to Mal Kelapa Gading. Features rooftop infinity pool, gym, and bright contemporary rooms in HARRIS signature orange.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Boulevard Bukit Gading Raya, Kelapa Gading Barat',
                    'city' => 'Kota Jakarta Utara',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/dRwhJ8sZGgPxr5RY9',
                'contact_email' => 'info.kelapagading@harrishotels.com',
                'contact_phone' => '+62 21 4585 8000',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 350_000,
                'rooms' => [
                    [
                        'name' => 'HARRIS Room', 'slug' => 'harris-room',
                        'description' => '28 sqm room with king or twin bed, signature HARRIS bright orange decor, and shower-only bathroom.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 28.00, 'base_rate' => 1_050_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'HARRIS Family', 'slug' => 'harris-family',
                        'description' => '40 sqm room with two queen beds + sofa bed, family-friendly configuration.',
                        'max_pax' => 4, 'bed_type' => 'Twin Queen + Sofa', 'area_sqm' => 40.00, 'base_rate' => 1_550_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'pop-hotel-kemayoran',
                'name' => 'POP! Hotel Kemayoran',
                'description' => 'Budget-friendly 2-star hotel near JIEXPO, ideal for cost-conscious solo exhibitors. Compact eco-friendly rooms, lobby coffee bar, and free WiFi throughout.',
                'star_rating' => 2,
                'address' => [
                    'street' => 'Jl. Industri Raya I, Gunung Sahari Selatan, Kemayoran',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/wjBcrWyHNgEDX5RC8',
                'contact_email' => 'reservation.kemayoran@pophotels.com',
                'contact_phone' => '+62 21 4280 1234',
                'cancellation_policy' => 'Free cancellation up to 24 hours before arrival.',
                'commission_rate' => 13.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 5.00,
                'is_active' => true,
                '_transfer_price' => 250_000,
                'rooms' => [
                    [
                        'name' => 'POP! Room', 'slug' => 'pop-room',
                        'description' => '14 sqm compact eco-friendly room with double bed, hot shower, and 32-inch TV. Bring your own toiletries.',
                        'max_pax' => 2, 'bed_type' => 'Double', 'area_sqm' => 14.00, 'base_rate' => 425_000,
                        'breakfast_included' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Near ICE BSD - BSD City, Tangerang.
     */
    private function iceBsd(): array
    {
        return [
            [
                'slug' => 'the-grove-suites-bsd-city',
                'name' => 'The Grove Suites by Grand Aston BSD City',
                'description' => '5-star all-suite hotel directly opposite ICE BSD with covered walkway access. Each suite features a fully equipped kitchen, separate living area, and BSD City skyline views.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Jl. BSD Grand Boulevard, BSD City',
                    'city' => 'Kota Tangerang Selatan',
                    'province' => 'Banten',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/Ko1RrFRJsAQ8eD3R9',
                'contact_email' => 'reservation@thegrove-bsd.com',
                'contact_phone' => '+62 21 5316 1888',
                'cancellation_policy' => 'Free cancellation up to 72 hours before check-in.',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 10.00,
                'is_active' => true,
                '_transfer_price' => 400_000,
                'rooms' => [
                    [
                        'name' => 'One-Bedroom Suite', 'slug' => 'one-bedroom-suite',
                        'description' => '60 sqm suite with separate bedroom, living area, full kitchen, and washing machine.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 60.00, 'base_rate' => 1_900_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Two-Bedroom Suite', 'slug' => 'two-bedroom-suite',
                        'description' => '95 sqm suite with two bedrooms, two bathrooms, and shared living/kitchen area.',
                        'max_pax' => 4, 'bed_type' => 'King + Twin', 'area_sqm' => 95.00, 'base_rate' => 3_200_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'aryaduta-bsd',
                'name' => 'Aryaduta BSD',
                'description' => '5-star hotel inside BSD Boulevard, ~500 m from ICE BSD. Houses Aryaduta Grand Ballroom, lagoon-shaped pool, kids club, and three restaurants.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Jl. Pahlawan Seribu, BSD Boulevard, BSD City',
                    'city' => 'Kota Tangerang Selatan',
                    'province' => 'Banten',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/UbZJxJp3JaR4yPHQ7',
                'contact_email' => 'reservation.bsd@aryaduta.com',
                'contact_phone' => '+62 21 5316 6666',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 350_000,
                'rooms' => [
                    [
                        'name' => 'Deluxe Room', 'slug' => 'deluxe-room-aryaduta',
                        'description' => '36 sqm room with king or twin bed, lagoon pool view, and rain shower bathroom.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 36.00, 'base_rate' => 1_350_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Premier Lagoon', 'slug' => 'premier-lagoon',
                        'description' => '42 sqm premium room with direct lagoon view, walk-in closet, and bathtub.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 42.00, 'base_rate' => 1_750_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Aryaduta Suite', 'slug' => 'aryaduta-suite',
                        'description' => '70 sqm suite with separate living room, executive lounge access, and lagoon panoramic view.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 70.00, 'base_rate' => 3_400_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'swiss-belhotel-serpong',
                'name' => 'Swiss-Belhotel Serpong',
                'description' => '4-star hotel in BSD City, ~3 km from ICE BSD. Features rooftop pool, Swiss-Cafe restaurant, and meeting facilities ideal for corporate functions.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Pahlawan Seribu Lengkong Karya, Serpong',
                    'city' => 'Kota Tangerang Selatan',
                    'province' => 'Banten',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/cTW7AjJgEDgnNH8H6',
                'contact_email' => 'reservation@swiss-belhotelserpong.com',
                'contact_phone' => '+62 21 8082 1888',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 325_000,
                'rooms' => [
                    [
                        'name' => 'Deluxe Room', 'slug' => 'deluxe-swiss',
                        'description' => '32 sqm room with king or twin bed, rain shower, and Swiss Advantage amenities.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 32.00, 'base_rate' => 1_050_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Grand Deluxe', 'slug' => 'grand-deluxe-swiss',
                        'description' => '38 sqm room with city view and bathtub.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 38.00, 'base_rate' => 1_250_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Junior Suite', 'slug' => 'junior-suite-swiss',
                        'description' => '54 sqm suite with separate sitting area and executive amenities.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 54.00, 'base_rate' => 1_950_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'mercure-serpong-alam-sutera',
                'name' => 'Mercure Serpong Alam Sutera',
                'description' => '4-star hotel in Alam Sutera, ~5 km from ICE BSD. Connected to Mall@Alam Sutera, features outdoor pool, fitness center, and Mercure signature breakfast.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Jalur Sutera Barat Kav 16, Alam Sutera, Serpong Utara',
                    'city' => 'Kota Tangerang Selatan',
                    'province' => 'Banten',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/o4WzGsxQ4qBgZGgz5',
                'contact_email' => 'h7780@accor.com',
                'contact_phone' => '+62 21 2222 4000',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 375_000,
                'rooms' => [
                    [
                        'name' => 'Superior Room', 'slug' => 'superior-mercure-as',
                        'description' => '30 sqm room with king or twin bed, ergonomic work desk, and Mercure signature bedding.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 30.00, 'base_rate' => 1_100_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Privilege Room', 'slug' => 'privilege-mercure-as',
                        'description' => '32 sqm upgraded room on higher floor with welcome amenities and lounge access.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 32.00, 'base_rate' => 1_400_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'santika-premiere-bsd',
                'name' => 'Hotel Santika Premiere ICE BSD City',
                'description' => '4-star hotel directly adjacent to ICE BSD (across the road), the most convenient pick for exhibition visitors. Outdoor pool, Carita Restaurant, and conference facilities.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. BSD Grand Boulevard No. 1, BSD City',
                    'city' => 'Kota Tangerang Selatan',
                    'province' => 'Banten',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/W9w7DhRTGcvAHc1y5',
                'contact_email' => 'icebsd@santika.com',
                'contact_phone' => '+62 21 5316 5588',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 10.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 300_000,
                'rooms' => [
                    [
                        'name' => 'Superior Room', 'slug' => 'superior-santika-bsd',
                        'description' => '26 sqm room with king or twin bed, work desk, and 40-inch LED TV.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 26.00, 'base_rate' => 950_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Deluxe Room', 'slug' => 'deluxe-santika-bsd',
                        'description' => '32 sqm room with pool view and bathtub.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 32.00, 'base_rate' => 1_200_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Premier Suite', 'slug' => 'premier-suite-santika',
                        'description' => '52 sqm suite with separate sitting area, ideal for exhibitors needing extra workspace.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 52.00, 'base_rate' => 1_950_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Near NICE PIK 2 - Pantai Indah Kapuk 2, Jakarta Utara.
     */
    private function nicePik2(): array
    {
        return [
            [
                'slug' => 'pullman-jakarta-pik-avenue',
                'name' => 'Pullman Jakarta Pantai Indah Kapuk',
                'description' => 'Brand new 5-star hotel in PIK 2, ~3 km from NICE convention center. Connected to PIK Avenue Mall with rooftop infinity pool, three signature restaurants, and ocean-side ballrooms.',
                'star_rating' => 5,
                'address' => [
                    'street' => 'Jl. PIK Boulevard, Pantai Indah Kapuk 2',
                    'city' => 'Kota Jakarta Utara',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/r5LXTHX5fAFD1GVe9',
                'contact_email' => 'reservation.pullmanpik@accor.com',
                'contact_phone' => '+62 21 5099 8888',
                'cancellation_policy' => 'Free cancellation up to 72 hours before arrival.',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 10.00,
                'is_active' => true,
                '_transfer_price' => 450_000,
                'rooms' => [
                    [
                        'name' => 'Superior Room', 'slug' => 'superior-pullman',
                        'description' => '38 sqm room with sea or city view, king or twin bed, and Pullman signature bedding.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 38.00, 'base_rate' => 2_100_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Executive Room', 'slug' => 'executive-pullman',
                        'description' => '42 sqm room on Executive Floor with lounge access (breakfast, all-day refreshments, evening cocktails).',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 42.00, 'base_rate' => 2_900_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Junior Suite Ocean View', 'slug' => 'junior-suite-pullman',
                        'description' => '64 sqm suite with separate sitting area and unobstructed ocean view.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 64.00, 'base_rate' => 4_200_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'swiss-belhotel-mangga-besar',
                'name' => 'Swiss-Belhotel Mangga Besar',
                'description' => '4-star hotel ~12 km from PIK 2, popular for visitors who prefer Mangga Besar/Old Town. Rooftop pool, all-day dining, and easy access to Pluit/Ancol attractions.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Kartini Raya No.57, Mangga Besar',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/Cm5tvgyJpyx7m7y7A',
                'contact_email' => 'reservation@swiss-belhotelmanggabesar.com',
                'contact_phone' => '+62 21 6386 6688',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 375_000,
                'rooms' => [
                    [
                        'name' => 'Deluxe Room', 'slug' => 'deluxe-swiss-mb',
                        'description' => '30 sqm room with king or twin bed and rain shower bathroom.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 30.00, 'base_rate' => 950_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Grand Deluxe', 'slug' => 'grand-deluxe-swiss-mb',
                        'description' => '36 sqm upgraded room with city view and bathtub.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 36.00, 'base_rate' => 1_150_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'aston-pluit-hotel-residence',
                'name' => 'ASTON Pluit Hotel & Residence',
                'description' => '4-star hotel in Pluit area, ~8 km from PIK 2. Features rooftop infinity pool, multiple F&B outlets, and meeting rooms suitable for small corporate events.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Pluit Selatan Raya Kav.2, Penjaringan',
                    'city' => 'Kota Jakarta Utara',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/PVHy7v9D6e7L7v9o9',
                'contact_email' => 'reservation@astonpluit.com',
                'contact_phone' => '+62 21 666 71888',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 400_000,
                'rooms' => [
                    [
                        'name' => 'Superior Room', 'slug' => 'superior-aston-pluit',
                        'description' => '32 sqm room with king or twin bed, work desk, and shower-only bathroom.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 32.00, 'base_rate' => 1_100_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Deluxe Suite', 'slug' => 'deluxe-suite-aston-pluit',
                        'description' => '54 sqm one-bedroom suite with pantry and separate living area, ideal for long stays.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 54.00, 'base_rate' => 1_950_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'mercure-convention-centre-ancol',
                'name' => 'Mercure Convention Centre Ancol',
                'description' => '4-star resort-style hotel in Ancol, ~12 km from PIK 2 with beachfront access. Features lagoon pool, three restaurants, kids club, and direct access to Ancol Dreamland attractions.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Pantai Indah, Taman Impian Jaya Ancol',
                    'city' => 'Kota Jakarta Utara',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/u52KaCRGGGdHWxxF7',
                'contact_email' => 'h2073@accor.com',
                'contact_phone' => '+62 21 6406000',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 425_000,
                'rooms' => [
                    [
                        'name' => 'Superior Room', 'slug' => 'superior-mercure-ancol',
                        'description' => '32 sqm room with garden view, king or twin bed, and Mercure signature amenities.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 32.00, 'base_rate' => 1_250_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Deluxe Lagoon View', 'slug' => 'deluxe-lagoon-ancol',
                        'description' => '34 sqm room overlooking the lagoon pool, with balcony.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 34.00, 'base_rate' => 1_550_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Family Studio', 'slug' => 'family-studio-ancol',
                        'description' => '46 sqm family room with king bed and two single beds, ideal for families with kids.',
                        'max_pax' => 4, 'bed_type' => 'King + 2 Singles', 'area_sqm' => 46.00, 'base_rate' => 2_200_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
            [
                'slug' => 'holiday-inn-jakarta-pik',
                'name' => 'Holiday Inn & Suites Jakarta Gajah Mada',
                'description' => '4-star hotel ~10 km from PIK 2, in Gajah Mada area. Two-tower property with separate hotel rooms and serviced apartment-style suites, rooftop pool, and full meeting facilities.',
                'star_rating' => 4,
                'address' => [
                    'street' => 'Jl. Hayam Wuruk No.6, Gambir',
                    'city' => 'Kota Jakarta Pusat',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
                'google_maps_link' => 'https://maps.app.goo.gl/Yqxx5G8Rxxr8XS9q7',
                'contact_email' => 'reservation.higm@ihg.com',
                'contact_phone' => '+62 21 2358 5888',
                'cancellation_policy' => 'Free cancellation up to 48 hours before arrival.',
                'commission_rate' => 11.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 7.50,
                'is_active' => true,
                '_transfer_price' => 400_000,
                'rooms' => [
                    [
                        'name' => 'Standard Room', 'slug' => 'standard-holiday-inn',
                        'description' => '32 sqm room with king or twin bed, work desk, and 43-inch smart TV.',
                        'max_pax' => 2, 'bed_type' => 'King or Twin', 'area_sqm' => 32.00, 'base_rate' => 1_300_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'Executive Room', 'slug' => 'executive-holiday-inn',
                        'description' => '36 sqm room with Executive Club access including breakfast and evening drinks.',
                        'max_pax' => 2, 'bed_type' => 'King', 'area_sqm' => 36.00, 'base_rate' => 1_750_000,
                        'breakfast_included' => true,
                    ],
                    [
                        'name' => 'One-Bedroom Suite', 'slug' => 'one-bedroom-suite-hi',
                        'description' => '60 sqm suite with pantry, dining area, and washing machine. Suitable for extended stays.',
                        'max_pax' => 3, 'bed_type' => 'King', 'area_sqm' => 60.00, 'base_rate' => 2_500_000,
                        'breakfast_included' => true,
                    ],
                ],
            ],
        ];
    }
}
