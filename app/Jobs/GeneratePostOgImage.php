<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Og\OgScreenshotService;
use App\Support\OgPages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;
use Throwable;

/**
 * Render a post's auto-generated OG card (featured image + gradient overlay +
 * title) with Browsershot into the og_image_generated collection. A manual
 * og_image upload always wins over this at the resource level.
 */
class GeneratePostOgImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    /**
     * Browsershot times out at 110s; stay under the pdf-batch supervisor's 120s.
     */
    public int $timeout = 115;

    /**
     * Bump when resources/views/og/post.blade.php changes visually, so the
     * idempotency hash misses and posts regenerate with the new design the
     * next time their job runs.
     */
    private const TEMPLATE_VERSION = 3;

    public function __construct(
        public int $postId,
        public bool $force = false,
    ) {
        $this->onQueue('pdf-batch');
    }

    public function handle(OgScreenshotService $screenshots): void
    {
        $post = Post::find($this->postId);

        if (! $post) {
            return;
        }

        $featured = $post->getFirstMedia('featured_image');

        if (! $featured) {
            if ($post->hasMedia('og_image_generated')) {
                $post->clearMediaCollection('og_image_generated');
                ResponseCache::clear(['blog-posts']);
            }

            return;
        }

        $title = (string) $post->title;
        $sourceHash = md5(self::TEMPLATE_VERSION.'|'.$title.'|'.$featured->uuid);
        $current = $post->getFirstMedia('og_image_generated');

        if (! $this->force && $current && $current->getCustomProperty('source_hash') === $sourceHash) {
            return;
        }

        $rawPath = storage_path("app/tmp/og-generate/{$this->postId}.png");
        $jpgPath = storage_path("app/tmp/og-generate/{$this->postId}.jpg");

        try {
            $html = view('og.post', [
                'title' => $title,
                'imageDataUri' => $this->imageDataUri($featured),
            ])->render();

            $screenshots->captureHtml($html, $rawPath);
            $screenshots->normalizeToOg($rawPath, $jpgPath);

            $post->addMedia($jpgPath)
                ->usingFileName("og-{$post->slug}.jpg")
                ->withCustomProperties([
                    'source_hash' => $sourceHash,
                    'width' => OgPages::WIDTH,
                    'height' => OgPages::HEIGHT,
                ])
                ->toMediaCollection('og_image_generated');

            ResponseCache::clear(['blog-posts']);
        } finally {
            File::delete(array_filter([$rawPath, $jpgPath], 'is_file'));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Post OG generation failed', [
            'post_id' => $this->postId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Inline the featured image as a data URI so Chrome renders without any
     * network access. Prefers the lg conversion (1200w) over the original to
     * keep the HTML payload small.
     */
    protected function imageDataUri(Media $media): string
    {
        $path = $media->hasGeneratedConversion('lg') ? $media->getPath('lg') : $media->getPath();

        if (! is_file($path) || ! is_readable($path)) {
            $path = $media->getPath();
        }

        if (is_file($path) && is_readable($path)) {
            $mime = mime_content_type($path) ?: ($media->mime_type ?: 'image/jpeg');

            return "data:{$mime};base64,".base64_encode(file_get_contents($path));
        }

        // Remote disk fallback: fetch over HTTP so the job still works when
        // media files are not locally readable.
        $url = $media->hasGeneratedConversion('lg') ? $media->getUrl('lg') : $media->getUrl();
        $contents = @file_get_contents(self::encodeMediaUrl($url));

        if ($contents === false) {
            throw new \RuntimeException("Unable to read featured image for post {$this->postId}");
        }

        $mime = $media->mime_type ?: 'image/jpeg';

        return "data:{$mime};base64,".base64_encode($contents);
    }

    /**
     * Media file names keep their original characters (spaces, multibyte
     * unicode such as U+202F in macOS screenshot names), but raw bytes like
     * these in an HTTP request line are rejected with 400 Bad Request.
     * Percent-encode every byte that is not a valid raw URL character, in
     * place - parse_url is not binary-safe (it mangles bytes >= 0x80), so the
     * URL is never split apart. Structural characters and existing percent
     * escapes pass through untouched.
     */
    public static function encodeMediaUrl(string $url): string
    {
        return preg_replace_callback(
            '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/',
            fn (array $match) => rawurlencode($match[0]),
            $url,
        );
    }
}
