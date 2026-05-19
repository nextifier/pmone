<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * Attaches photos to all hotels seeded by JakartaHotelsSeeder.
 *
 * Photos are stored locally in `database/seeders/hotel-photos/{hotel-slug}/`
 * and committed to git, so production seeding does not require external CDN
 * access at run time.
 *
 * Folder layout:
 *   database/seeders/hotel-photos/{slug}/featured.{jpg|png|webp}
 *   database/seeders/hotel-photos/{slug}/gallery/{1..N}.{jpg|png|webp}
 *   database/seeders/hotel-photos/{slug}/rooms/{room-slug}/{1..N}.{jpg|png|webp}
 *
 * Idempotent: skips hotels/rooms that already have media in the target collection.
 * Run with: php artisan db:seed --class=JakartaHotelsPhotosSeeder
 */
class JakartaHotelsPhotosSeeder extends Seeder
{
    private string $photosRoot;

    public function run(): void
    {
        $this->photosRoot = database_path('seeders/hotel-photos');

        if (! is_dir($this->photosRoot)) {
            $this->command?->error("Photos folder not found: {$this->photosRoot}");

            return;
        }

        foreach (scandir($this->photosRoot) as $slug) {
            if ($slug === '.' || $slug === '..' || ! is_dir("{$this->photosRoot}/{$slug}")) {
                continue;
            }

            $hotel = Hotel::where('slug', $slug)->first();
            if (! $hotel) {
                $this->command?->warn("Hotel not found: {$slug}, skipping.");

                continue;
            }

            $this->seedHotelMedia($hotel, $slug);
            $this->seedRoomMedia($hotel, $slug);

            $this->command?->info("Seeded photos: {$hotel->name}");
        }
    }

    private function seedHotelMedia(Hotel $hotel, string $slug): void
    {
        // Featured
        if ($hotel->getMedia('featured')->isEmpty()) {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $featured = "{$this->photosRoot}/{$slug}/featured.{$ext}";
                if (is_file($featured)) {
                    $this->safeAddFromFile($hotel, $featured, 'featured', "{$slug} featured");
                    break;
                }
            }
        }

        // Gallery
        $galleryDir = "{$this->photosRoot}/{$slug}/gallery";
        if (is_dir($galleryDir) && $hotel->getMedia('gallery')->isEmpty()) {
            foreach ($this->sortedFiles($galleryDir) as $file) {
                $this->safeAddFromFile($hotel, $file, 'gallery', "{$slug}/".basename($file));
            }
        }
    }

    private function seedRoomMedia(Hotel $hotel, string $slug): void
    {
        $roomsDir = "{$this->photosRoot}/{$slug}/rooms";
        if (! is_dir($roomsDir)) {
            return;
        }

        foreach (scandir($roomsDir) as $roomSlug) {
            if ($roomSlug === '.' || $roomSlug === '..') {
                continue;
            }
            $roomDir = "{$roomsDir}/{$roomSlug}";
            if (! is_dir($roomDir)) {
                continue;
            }

            $room = RoomType::where('hotel_id', $hotel->id)
                ->where('slug', $roomSlug)
                ->first();
            if (! $room || $room->getMedia('gallery')->isNotEmpty()) {
                continue;
            }

            foreach ($this->sortedFiles($roomDir) as $file) {
                $this->safeAddFromFile($room, $file, 'gallery', "{$slug}/{$roomSlug}/".basename($file));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function sortedFiles(string $dir): array
    {
        $files = array_filter(
            scandir($dir),
            fn (string $f): bool => $f !== '.' && $f !== '..' && preg_match('/\.(jpe?g|png|webp)$/i', $f),
        );
        natsort($files);

        return array_map(fn (string $f): string => "{$dir}/{$f}", array_values($files));
    }

    private function safeAddFromFile($model, string $path, string $collection, string $label): void
    {
        try {
            $model->addMedia($path)
                ->preservingOriginal()
                ->toMediaCollection($collection);
        } catch (\Throwable $e) {
            $this->command?->warn("  ! Failed to attach {$label}: ".$e->getMessage());
        }
    }
}
