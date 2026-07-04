<?php

namespace Tests\Support;

use App\Services\Og\OgScreenshotService;
use App\Support\OgPages;
use Illuminate\Support\Facades\File;

/**
 * Test double bound in the base TestCase: Browsershot cannot run in CI, and
 * the sync queue would otherwise invoke a real Chrome from any test that
 * saves a post with a featured image. Writes tiny GD images instead.
 */
class FakeOgScreenshotService extends OgScreenshotService
{
    /** @var list<string> */
    public array $capturedUrls = [];

    /** @var list<string> */
    public array $capturedHtml = [];

    public function captureUrl(string $url, string $outputPath): void
    {
        $this->capturedUrls[] = $url;
        $this->writeImage($outputPath);
    }

    public function captureHtml(string $html, string $outputPath): void
    {
        $this->capturedHtml[] = $html;
        $this->writeImage($outputPath);
    }

    public function normalizeToOg(string $sourcePath, string $targetPath, int $quality = 82): void
    {
        $this->writeImage($targetPath, OgPages::WIDTH, OgPages::HEIGHT);
    }

    protected function writeImage(string $path, int $width = 24, int $height = 12): void
    {
        File::ensureDirectoryExists(dirname($path));

        $image = imagecreatetruecolor($width, $height);

        if (str_ends_with(strtolower($path), '.png')) {
            imagepng($image, $path);
        } else {
            imagejpeg($image, $path, 80);
        }

        imagedestroy($image);
    }
}
