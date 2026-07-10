<?php

namespace App\Support;

class ImageDimensions
{
    /**
     * Check whether a raster image meets a minimum square dimension.
     *
     * SVG is vector and has no pixel dimensions, so it always passes. Any
     * file getimagesize() cannot read (corrupt or unsupported) fails closed.
     */
    public static function meetsMinimum(string $absolutePath, string $mimeType, int $min = 1000): bool
    {
        if ($mimeType === 'image/svg+xml') {
            return true;
        }

        if (! is_file($absolutePath)) {
            return false;
        }

        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return false;
        }

        [$width, $height] = $info;

        return $width >= $min && $height >= $min;
    }
}
