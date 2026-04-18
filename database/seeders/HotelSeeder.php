<?php

namespace Database\Seeders;

use App\Enums\TransferDirection;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = [
            [
                'name' => 'Grand Mercure Jakarta Kemayoran',
                'slug' => 'grand-mercure-jakarta-kemayoran',
                'description' => '4-star hotel in Kemayoran district, near the JIExpo venue.',
                'address' => 'Jl. H. Benyamin Suaeb, Kemayoran',
                'city' => 'Jakarta',
                'commission_rate' => 10.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 5.00,
                'contact_email' => 'reservation@grandmercure-kemayoran.com',
                'contact_phone' => '+62 21 2664 5555',
                'rooms' => [
                    ['name' => 'Superior King', 'base_rate' => 1200000, 'max_pax' => 2, 'bed_type' => 'King'],
                    ['name' => 'Deluxe Twin', 'base_rate' => 1450000, 'max_pax' => 2, 'bed_type' => 'Twin'],
                    ['name' => 'Executive Suite', 'base_rate' => 2800000, 'max_pax' => 3, 'bed_type' => 'King'],
                ],
                'transfers' => [
                    ['label' => 'Airport Sedan (CGK)', 'direction' => TransferDirection::Both, 'vehicle_type' => 'Sedan', 'max_pax' => 3, 'price' => 350000],
                    ['label' => 'Airport MPV (CGK)', 'direction' => TransferDirection::Both, 'vehicle_type' => 'MPV', 'max_pax' => 6, 'price' => 550000],
                ],
            ],
            [
                'name' => 'Holiday Inn Jakarta Kemayoran',
                'slug' => 'holiday-inn-jakarta-kemayoran',
                'description' => 'Modern accommodation with easy access to the exhibition center.',
                'address' => 'Jl. Angkasa Mulia, Kemayoran',
                'city' => 'Jakarta',
                'commission_rate' => 12.00,
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 5.00,
                'contact_email' => 'reservation@hi-jakarta.com',
                'contact_phone' => '+62 21 4288 9999',
                'rooms' => [
                    ['name' => 'Standard Queen', 'base_rate' => 950000, 'max_pax' => 2, 'bed_type' => 'Queen'],
                    ['name' => 'Deluxe King', 'base_rate' => 1350000, 'max_pax' => 2, 'bed_type' => 'King'],
                ],
                'transfers' => [
                    ['label' => 'Airport Shuttle', 'direction' => TransferDirection::Both, 'vehicle_type' => 'Shuttle', 'max_pax' => 8, 'price' => 250000],
                ],
            ],
        ];

        foreach ($hotels as $payload) {
            $rooms = $payload['rooms'];
            $transfers = $payload['transfers'];
            unset($payload['rooms'], $payload['transfers']);

            $hotel = Hotel::firstOrCreate(['slug' => $payload['slug']], $payload);

            foreach ($rooms as $room) {
                RoomType::firstOrCreate(
                    ['hotel_id' => $hotel->id, 'name' => $room['name']],
                    array_merge($room, [
                        'breakfast_included' => true,
                        'amenities' => ['WiFi', 'AC', 'TV', 'Minibar'],
                        'is_active' => true,
                    ])
                );
            }

            foreach ($transfers as $transfer) {
                HotelTransferOption::firstOrCreate(
                    ['hotel_id' => $hotel->id, 'label' => $transfer['label']],
                    array_merge($transfer, ['is_active' => true])
                );
            }

            $this->command->info("Seeded hotel: {$hotel->name}");
        }
    }
}
