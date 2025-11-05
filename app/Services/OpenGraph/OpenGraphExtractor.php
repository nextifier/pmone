<?php

namespace App\Services\OpenGraph;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenGraphExtractor
{
    public function __construct(
        private int $timeout = 10,
        private int $maxRedirects = 3,
    ) {
    }

    /**
     * Extract OpenGraph metadata from a URL.
     *
     * @return array{og_title: ?string, og_description: ?string, og_image: ?string, og_type: string}
     */
    public function extract(string $url): array
    {
        $defaultMetadata = [
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_type' => 'website',
        ];

        try {
            // Validate URL format
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning('Invalid URL format for OpenGraph extraction', ['url' => $url]);

                return $defaultMetadata;
            }

            // Fetch HTML content with timeout and error handling
            $response = Http::timeout($this->timeout)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => $this->maxRedirects,
                        'strict' => true,
                        'referer' => true,
                    ],
                    'verify' => false, // Allow self-signed certificates for development
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; PMOne/1.0; +https://pmone.id)',
                ])
                ->get($url);

            // Check if request was successful
            if (! $response->successful()) {
                Log::info('Failed to fetch URL for OpenGraph extraction', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return $defaultMetadata;
            }

            $html = $response->body();

            // Check if response is HTML
            $contentType = $response->header('Content-Type') ?? '';
            if (! str_contains(strtolower($contentType), 'text/html')) {
                Log::info('URL is not HTML content', [
                    'url' => $url,
                    'content_type' => $contentType,
                ]);

                return $defaultMetadata;
            }

            // Parse HTML and extract OG tags
            return $this->parseHtml($html, $url);

        } catch (Throwable $e) {
            Log::error('Error extracting OpenGraph metadata', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $defaultMetadata;
        }
    }

    /**
     * Parse HTML and extract OpenGraph meta tags.
     *
     * @return array{og_title: ?string, og_description: ?string, og_image: ?string, og_type: string}
     */
    private function parseHtml(string $html, string $baseUrl): array
    {
        $metadata = [
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_type' => 'website',
        ];

        try {
            // Suppress HTML parsing warnings
            $previousValue = libxml_use_internal_errors(true);

            $dom = new DOMDocument;
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

            libxml_clear_errors();
            libxml_use_internal_errors($previousValue);

            $xpath = new DOMXPath($dom);

            // Extract OG meta tags
            $ogTags = [
                'og:title' => 'og_title',
                'og:description' => 'og_description',
                'og:image' => 'og_image',
                'og:type' => 'og_type',
            ];

            foreach ($ogTags as $property => $key) {
                $nodes = $xpath->query("//meta[@property='{$property}']");
                if ($nodes && $nodes->length > 0) {
                    $content = $nodes->item(0)->getAttribute('content');
                    if (! empty($content)) {
                        $metadata[$key] = trim($content);
                    }
                }
            }

            // Fallback to standard meta tags if OG tags not found
            if (empty($metadata['og_title'])) {
                $metadata['og_title'] = $this->extractFallbackTitle($xpath);
            }

            if (empty($metadata['og_description'])) {
                $metadata['og_description'] = $this->extractFallbackDescription($xpath);
            }

            // Convert relative image URLs to absolute
            if (! empty($metadata['og_image']) && ! filter_var($metadata['og_image'], FILTER_VALIDATE_URL)) {
                $metadata['og_image'] = $this->resolveUrl($baseUrl, $metadata['og_image']);
            }

        } catch (Throwable $e) {
            Log::warning('Error parsing HTML for OpenGraph metadata', [
                'error' => $e->getMessage(),
            ]);
        }

        return $metadata;
    }

    /**
     * Extract title from standard meta tags as fallback.
     */
    private function extractFallbackTitle(DOMXPath $xpath): ?string
    {
        // Try <title> tag
        $nodes = $xpath->query('//title');
        if ($nodes && $nodes->length > 0) {
            $title = trim($nodes->item(0)->textContent);
            if (! empty($title)) {
                return $title;
            }
        }

        // Try meta name="title"
        $nodes = $xpath->query("//meta[@name='title']");
        if ($nodes && $nodes->length > 0) {
            $content = $nodes->item(0)->getAttribute('content');
            if (! empty($content)) {
                return trim($content);
            }
        }

        return null;
    }

    /**
     * Extract description from standard meta tags as fallback.
     */
    private function extractFallbackDescription(DOMXPath $xpath): ?string
    {
        $descriptionMetaTags = ['description', 'Description'];

        foreach ($descriptionMetaTags as $name) {
            $nodes = $xpath->query("//meta[@name='{$name}']");
            if ($nodes && $nodes->length > 0) {
                $content = $nodes->item(0)->getAttribute('content');
                if (! empty($content)) {
                    return trim($content);
                }
            }
        }

        return null;
    }

    /**
     * Resolve relative URL to absolute URL.
     */
    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        // Already absolute URL
        if (filter_var($relativeUrl, FILTER_VALIDATE_URL)) {
            return $relativeUrl;
        }

        $parsedBase = parse_url($baseUrl);

        // Protocol-relative URL (//example.com/image.jpg)
        if (str_starts_with($relativeUrl, '//')) {
            return ($parsedBase['scheme'] ?? 'https').':'.$relativeUrl;
        }

        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';
        $port = isset($parsedBase['port']) ? ':'.$parsedBase['port'] : '';

        // Absolute path (/image.jpg)
        if (str_starts_with($relativeUrl, '/')) {
            return "{$scheme}://{$host}{$port}{$relativeUrl}";
        }

        // Relative path (image.jpg or ./image.jpg)
        $path = $parsedBase['path'] ?? '';
        $path = preg_replace('/\/[^\/]*$/', '/', $path);

        return "{$scheme}://{$host}{$port}{$path}{$relativeUrl}";
    }
}
