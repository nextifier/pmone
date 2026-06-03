<?php

namespace App\Services\Shaders;

use enshrined\svgSanitize\Sanitizer;
use Imagick;
use ImagickPixel;
use RuntimeException;
use SplFixedArray;

/**
 * Converts an SVG/PNG logo into a 512x512 signed distance field (SDF) binary that
 * the `shaders` library consumes via the `shapeSdfUrl` prop.
 *
 * Quality comes from an anti-aliased ("tiny-sdf" / Gustavson) distance transform:
 * the boundary is placed sub-pixel from the rasterized anti-aliased coverage rather
 * than a hard binary mask, which is what keeps the gradient smooth (no refraction
 * noise). Internally we rasterize and run the EDT at `size` (default 1024) then box
 * the distance field down to 512 by centre-sampling.
 *
 * Output format (must match the library loader exactly):
 *  - 512x512, row-major, origin top-left.
 *  - Signed distance in UV units = pixelDistance / 512, negative inside.
 *  - Compact Uint16: u = round((v + 1) * 32767.5), little-endian. Exactly
 *    512*512*2 = 524288 bytes (this length is how the loader detects the format).
 */
class SvgToSdfGenerator
{
    public const OUTPUT_SIZE = 512;

    private const BIG = 1e20;

    private const MARGIN = 0.06;

    /**
     * Convert raw SVG/PNG file contents into the 524288-byte SDF binary string.
     */
    public function generate(string $contents, string $mime, int $size = 1024): string
    {
        if ($size % self::OUTPUT_SIZE !== 0 || $size < self::OUTPUT_SIZE) {
            throw new RuntimeException('Working size must be a multiple of 512.');
        }

        $coverage = $this->rasterizeCoverage($contents, $mime, $size);
        $sdf = $this->coverageToSignedSdf($coverage, $size);
        $out = $size === self::OUTPUT_SIZE ? $sdf : $this->downsample($sdf, $size, self::OUTPUT_SIZE);

        return $this->encodeUint16($out);
    }

    /**
     * Rasterize the file to a `size`x`size` canvas (fit + margin, centered, AA) and
     * return per-pixel coverage in [0,1]. Prefers the alpha channel; for a fully
     * opaque image (logo on a solid background) it falls back to a luminance
     * difference from the detected background colour.
     *
     * @return SplFixedArray<float>
     */
    private function rasterizeCoverage(string $contents, string $mime, int $size): SplFixedArray
    {
        $isSvg = str_contains($mime, 'svg');

        $image = new Imagick;
        $image->setBackgroundColor(new ImagickPixel('transparent'));

        if ($isSvg) {
            $image->setResolution(300, 300);
            $image->readImageBlob($this->sanitizeSvg($contents));
        } else {
            $image->readImageBlob($contents);
        }

        $image->setImageFormat('png32');

        $w0 = $image->getImageWidth();
        $h0 = $image->getImageHeight();
        if ($w0 < 1 || $h0 < 1) {
            throw new RuntimeException('Could not read the image dimensions.');
        }

        $avail = (int) round($size * (1 - 2 * self::MARGIN));
        $scale = min($avail / $w0, $avail / $h0);
        $dw = max(1, (int) round($w0 * $scale));
        $dh = max(1, (int) round($h0 * $scale));
        $image->resizeImage($dw, $dh, Imagick::FILTER_LANCZOS, 1);

        $canvas = new Imagick;
        $canvas->newImage($size, $size, new ImagickPixel('transparent'));
        $canvas->setImageFormat('png32');
        $canvas->compositeImage(
            $image,
            Imagick::COMPOSITE_OVER,
            (int) round(($size - $dw) / 2),
            (int) round(($size - $dh) / 2)
        );

        $n = $size * $size;
        $alpha = $canvas->exportImagePixels(0, 0, $size, $size, 'A', Imagick::PIXEL_FLOAT);

        $hasAlpha = false;
        for ($i = 0; $i < $n; $i++) {
            if ($alpha[$i] < 0.996) {
                $hasAlpha = true;
                break;
            }
        }

        $coverage = new SplFixedArray($n);
        $inside = 0;

        if ($hasAlpha) {
            for ($i = 0; $i < $n; $i++) {
                $coverage[$i] = $alpha[$i];
                if ($alpha[$i] > 0.5) {
                    $inside++;
                }
            }
        } else {
            // Opaque image: derive a binary mask from luminance vs the corner colour.
            $rgb = $canvas->exportImagePixels(0, 0, $size, $size, 'RGB', Imagick::PIXEL_FLOAT);
            $lum = static fn (int $p): float => 0.299 * $rgb[$p] + 0.587 * $rgb[$p + 1] + 0.114 * $rgb[$p + 2];
            $bg = $lum(0);
            for ($i = 0; $i < $n; $i++) {
                $v = abs($lum($i * 3) - $bg) > 0.15 ? 1.0 : 0.0;
                $coverage[$i] = $v;
                $inside += (int) $v;
            }
        }

        $image->clear();
        $canvas->clear();

        if ($inside === 0) {
            throw new RuntimeException('No shape detected. Use a logo with a solid area or a transparent background.');
        }

        return $coverage;
    }

    /**
     * Sanitize untrusted SVG (strip scripts, external refs, entities) before it ever
     * reaches Imagick, to prevent SSRF/XXE/local-file disclosure.
     */
    private function sanitizeSvg(string $contents): string
    {
        $sanitizer = new Sanitizer;
        $clean = $sanitizer->sanitize($contents);

        if (! is_string($clean) || ! str_contains($clean, '<svg')) {
            throw new RuntimeException('The SVG could not be processed safely.');
        }

        return $clean;
    }

    /**
     * tiny-sdf seeding from coverage + signed Euclidean distance transform, scaled to
     * UV units (positive outside, negative inside).
     *
     * @param  SplFixedArray<float>  $coverage
     * @return SplFixedArray<float>
     */
    private function coverageToSignedSdf(SplFixedArray $coverage, int $size): SplFixedArray
    {
        $n = $size * $size;
        $outer = new SplFixedArray($n);
        $inner = new SplFixedArray($n);

        for ($i = 0; $i < $n; $i++) {
            $a = $coverage[$i];
            if ($a >= 1.0) {
                $outer[$i] = 0.0;
                $inner[$i] = self::BIG;
            } elseif ($a <= 0.0) {
                $outer[$i] = self::BIG;
                $inner[$i] = 0.0;
            } else {
                $o = max(0.0, 0.5 - $a);
                $outer[$i] = $o * $o;
                $ii = max(0.0, $a - 0.5);
                $inner[$i] = $ii * $ii;
            }
        }

        $this->edt2d($outer, $size, $size);
        $this->edt2d($inner, $size, $size);

        $sdf = new SplFixedArray($n);
        for ($i = 0; $i < $n; $i++) {
            $sdf[$i] = (sqrt($outer[$i]) - sqrt($inner[$i])) / $size;
        }

        return $sdf;
    }

    /**
     * 1D squared distance transform (Felzenszwalb & Huttenlocher).
     *
     * @param  SplFixedArray<float>  $f
     * @param  SplFixedArray<float>  $d
     * @param  SplFixedArray<int>  $v
     * @param  SplFixedArray<float>  $z
     */
    private function edt1d(SplFixedArray $f, SplFixedArray $d, SplFixedArray $v, SplFixedArray $z, int $n): void
    {
        $k = 0;
        $v[0] = 0;
        $z[0] = -self::BIG;
        $z[1] = self::BIG;
        for ($q = 1; $q < $n; $q++) {
            $s = (($f[$q] + $q * $q) - ($f[$v[$k]] + $v[$k] * $v[$k])) / (2 * $q - 2 * $v[$k]);
            while ($s <= $z[$k]) {
                $k--;
                $s = (($f[$q] + $q * $q) - ($f[$v[$k]] + $v[$k] * $v[$k])) / (2 * $q - 2 * $v[$k]);
            }
            $k++;
            $v[$k] = $q;
            $z[$k] = $s;
            $z[$k + 1] = self::BIG;
        }
        $k = 0;
        for ($q = 0; $q < $n; $q++) {
            while ($z[$k + 1] < $q) {
                $k++;
            }
            $dx = $q - $v[$k];
            $d[$q] = $dx * $dx + $f[$v[$k]];
        }
    }

    /**
     * In-place separable 2D squared EDT over a row-major grid of seed costs.
     *
     * @param  SplFixedArray<float>  $grid
     */
    private function edt2d(SplFixedArray $grid, int $w, int $h): void
    {
        $m = max($w, $h);
        $f = new SplFixedArray($m);
        $d = new SplFixedArray($m);
        $v = new SplFixedArray($m);
        $z = new SplFixedArray($m + 1);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $f[$y] = $grid[$y * $w + $x];
            }
            $this->edt1d($f, $d, $v, $z, $h);
            for ($y = 0; $y < $h; $y++) {
                $grid[$y * $w + $x] = $d[$y];
            }
        }

        for ($y = 0; $y < $h; $y++) {
            $off = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $f[$x] = $grid[$off + $x];
            }
            $this->edt1d($f, $d, $v, $z, $w);
            for ($x = 0; $x < $w; $x++) {
                $grid[$off + $x] = $d[$x];
            }
        }
    }

    /**
     * Centre-sample (central 2x2 of each block) down to `dstSize`. Avoids the corner
     * rounding a box average would cause while keeping sub-pixel accuracy.
     *
     * @param  SplFixedArray<float>  $src
     * @return SplFixedArray<float>
     */
    private function downsample(SplFixedArray $src, int $srcSize, int $dstSize): SplFixedArray
    {
        $factor = intdiv($srcSize, $dstSize);
        $o = intdiv($factor, 2) - 1;
        $dst = new SplFixedArray($dstSize * $dstSize);

        for ($y = 0; $y < $dstSize; $y++) {
            for ($x = 0; $x < $dstSize; $x++) {
                $r0 = ($y * $factor + $o) * $srcSize + ($x * $factor + $o);
                $r1 = $r0 + $srcSize;
                $dst[$y * $dstSize + $x] = ($src[$r0] + $src[$r0 + 1] + $src[$r1] + $src[$r1 + 1]) * 0.25;
            }
        }

        return $dst;
    }

    /**
     * Encode the signed UV field as little-endian Uint16 (the compact format).
     *
     * @param  SplFixedArray<float>  $sdf
     */
    private function encodeUint16(SplFixedArray $sdf): string
    {
        $n = $sdf->getSize();
        $values = [];
        for ($i = 0; $i < $n; $i++) {
            $u = (int) round(($sdf[$i] + 1) * 32767.5);
            $values[$i] = $u < 0 ? 0 : ($u > 65535 ? 65535 : $u);
        }

        return pack('v*', ...$values);
    }
}
