<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Renders an attendee check-in QR as a PNG using GD only (no Imagick extension,
 * which production lacks). BaconQrCode ships only Imagick/SVG/EPS backends, so we
 * paint the raw module matrix onto a GD canvas as solid (square) modules with a
 * 4-module quiet zone. Shared by the public qr.png endpoint and the e-ticket email
 * (which embeds the bytes inline so the code shows without "load images").
 */
class AttendeeQrImage
{
    public static function png(string $value): string
    {
        $matrix = Encoder::encode($value, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth();
        $margin = 4;
        $scale = 10;
        $dimension = ($modules + $margin * 2) * $scale;

        $image = imagecreatetruecolor($dimension, $dimension);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $dimension, $dimension, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $left = ($x + $margin) * $scale;
                    $top = ($y + $margin) * $scale;
                    imagefilledrectangle($image, $left, $top, $left + $scale - 1, $top + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
