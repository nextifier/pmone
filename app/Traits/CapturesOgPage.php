<?php

namespace App\Traits;

use App\Models\Project;
use App\Services\Og\OgScreenshotService;
use App\Support\OgPages;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait CapturesOgPage
{
    /**
     * Screenshot one live website page and store it as the project's OG image
     * for that page key. Shared by the single-page and capture-all jobs.
     */
    protected function captureOgPage(
        OgScreenshotService $screenshots,
        Project $project,
        string $websiteUrl,
        string $pageKey,
        string $tmpBasename,
    ): Media {
        $url = rtrim($websiteUrl, '/').OgPages::pathFor($pageKey);
        $rawPath = storage_path("app/tmp/og-capture/{$tmpBasename}.png");
        $jpgPath = storage_path("app/tmp/og-capture/{$tmpBasename}.jpg");

        try {
            $screenshots->captureUrl($url, $rawPath);
            $screenshots->normalizeToOg($rawPath, $jpgPath);

            return $project->addMedia($jpgPath)
                ->usingFileName("og-{$pageKey}.jpg")
                ->withCustomProperties([
                    'width' => OgPages::WIDTH,
                    'height' => OgPages::HEIGHT,
                    'source' => 'capture',
                ])
                ->toMediaCollection(OgPages::collectionFor($pageKey));
        } finally {
            File::delete(array_filter([$rawPath, $jpgPath], 'is_file'));
        }
    }
}
