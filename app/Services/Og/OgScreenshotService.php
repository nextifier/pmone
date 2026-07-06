<?php

namespace App\Services\Og;

use App\Support\OgPages;
use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Single Browsershot entry point for OG image generation so queued jobs can
 * mock the browser away in tests (Browsershot cannot run in CI).
 */
class OgScreenshotService
{
    /**
     * MacBook-Air-class viewport width so sites render their full desktop
     * layout, with the height derived from the OG aspect ratio (1440:756 =
     * 1200:630) so the capture downscales to the final size without cropping.
     */
    public const CAPTURE_WIDTH = 1440;

    public const CAPTURE_HEIGHT = 756;

    /**
     * Screenshot a live page at a desktop viewport, 2x for crisp text
     * (2880x1512 raw output). We deliberately do NOT waitUntilNetworkIdle:
     * heavy event sites (three.js/shaders, analytics beacons, web fonts,
     * autoplay video) keep the network busy forever so networkidle0 never
     * fires and the capture hangs until the process timeout. Instead we let
     * Browsershot use Puppeteer's default `waitUntil: 'load'` (bounded) and
     * rely on the fixed delay to let the page hydrate and paint before capture.
     *
     * enable-unsafe-swiftshader keeps WebGL working on the GPU-less production
     * server: Chrome 128+ disables the SwiftShader (software) WebGL fallback by
     * default, which would render shader/three.js heroes (e.g. the Global AI
     * Expo home) as a blank canvas. The flag is a harmless no-op for pages that
     * do not use WebGL.
     */
    public function captureUrl(string $url, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $this->configureBinaries(Browsershot::url($url))
            ->windowSize(self::CAPTURE_WIDTH, self::CAPTURE_HEIGHT)
            ->deviceScaleFactor(2)
            ->emulateMediaFeatures([
                ['name' => 'prefers-reduced-motion', 'value' => 'reduce'],
            ])
            ->addChromiumArguments(['enable-unsafe-swiftshader'])
            ->setDelay(5000)
            ->dismissDialogs()
            ->timeout(70)
            ->save($outputPath);
    }

    /**
     * Screenshot a self-contained HTML document (assets inlined as data URIs,
     * so no network wait is needed).
     */
    public function captureHtml(string $html, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $this->configureBinaries(Browsershot::html($html))
            ->windowSize(OgPages::WIDTH, OgPages::HEIGHT)
            ->deviceScaleFactor(2)
            ->setDelay(500)
            ->dismissDialogs()
            ->timeout(70)
            ->save($outputPath);
    }

    /**
     * Downscale a raw capture to the canonical 1200x630 JPG. The target path
     * determines the encoded format, so it must end in .jpg.
     */
    public function normalizeToOg(string $sourcePath, string $targetPath, int $quality = 82): void
    {
        File::ensureDirectoryExists(dirname($targetPath));

        Image::useImageDriver(config('media-library.image_driver', 'imagick'))
            ->loadFile($sourcePath)
            ->fit(Fit::Crop, OgPages::WIDTH, OgPages::HEIGHT)
            ->quality($quality)
            ->optimize()
            ->save($targetPath);
    }

    /**
     * Apply the same node/chrome binary configuration spatie/laravel-pdf uses,
     * so screenshots work wherever ticket/reservation PDFs already do.
     */
    protected function configureBinaries(Browsershot $browsershot): Browsershot
    {
        $config = config('laravel-pdf.browsershot', []);

        if ($config['node_binary'] ?? null) {
            $browsershot->setNodeBinary($config['node_binary']);
        }

        if ($config['npm_binary'] ?? null) {
            $browsershot->setNpmBinary($config['npm_binary']);
        }

        if ($config['include_path'] ?? null) {
            $browsershot->setIncludePath($config['include_path']);
        }

        if ($config['chrome_path'] ?? null) {
            $browsershot->setChromePath($config['chrome_path']);
        }

        if ($config['node_modules_path'] ?? null) {
            $browsershot->setNodeModulePath($config['node_modules_path']);
        }

        if ($config['bin_path'] ?? null) {
            $browsershot->setBinPath($config['bin_path']);
        }

        if ($config['temp_path'] ?? null) {
            $browsershot->setCustomTempPath($config['temp_path']);
        }

        if ($config['write_options_to_file'] ?? false) {
            $browsershot->writeOptionsToFile();
        }

        if ($config['no_sandbox'] ?? false) {
            $browsershot->noSandbox();
        }

        return $browsershot;
    }
}
