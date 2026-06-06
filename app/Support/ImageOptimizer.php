<?php

namespace App\Support;

use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class ImageOptimizer
{
    /**
     * Downscale (never upscale) + compress an image file IN PLACE so stored
     * Media Library originals never exceed the configured cap, preventing huge
     * uploads from wasting disk. Keeps the original format.
     *
     * Safe to call on any file: non-raster types (svg/gif/pdf/video) and
     * already-small images are skipped. Never throws — failures are logged and
     * the file is left untouched so an upload is never blocked.
     *
     * @return bool true if the file was modified
     */
    public static function compressInPlace(string $absolutePath, ?int $maxDimension = null, ?int $quality = null): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return false;
        }

        // Only raster formats we can safely re-encode. Skip svg, animated gif, etc.
        $mime = $info['mime'] ?? '';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }

        $max = $maxDimension ?? (int) config('images.original_max_dimension', 1920);
        $quality ??= (int) config('images.original_quality', 82);
        $minBytes = (int) config('images.optimize_min_bytes', 512000);

        [$width, $height] = $info;
        $withinCap = $width <= $max && $height <= $max;

        // Already small enough -> leave as-is (idempotent, avoids needless re-encode).
        if ($withinCap && (@filesize($absolutePath) ?: 0) <= $minBytes) {
            return false;
        }

        try {
            $driver = config('media-library.image_driver', 'imagick');
            $image = Image::useImageDriver($driver)->loadFile($absolutePath);

            if (! $withinCap) {
                $image->fit(Fit::Max, $max, $max);
            }

            $image->quality($quality)->optimize()->save($absolutePath);

            return true;
        } catch (\Throwable $e) {
            logger()->warning('ImageOptimizer failed', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
