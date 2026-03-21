<?php

namespace App\Traits;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasMediaManager
{
    /**
     * Get media URL for a specific collection and conversion
     */
    public function getMediaUrl(string $collection, ?string $conversion = null): ?string
    {
        if ($conversion) {
            return $this->getFirstMediaUrl($collection, $conversion);
        }

        return $this->getFirstMediaUrl($collection);
    }

    /**
     * Get media URLs in structured format for a collection
     */
    public function getMediaUrls(string $collection): ?array
    {
        if (! $this->hasMedia($collection)) {
            return null;
        }

        $media = $this->getFirstMedia($collection);
        $originalUrl = $media->getUrl();

        $urls = [
            'url' => $originalUrl,
            'original' => $originalUrl,
            'caption' => $media->getCustomProperty('caption'),
            'alt' => $media->getCustomProperty('alt') ?? $media->name,
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
        ];

        foreach (['lqip', 'sm', 'md', 'lg', 'xl'] as $conversion) {
            $urls[$conversion] = $media->hasGeneratedConversion($conversion)
                ? $media->getUrl($conversion)
                : $originalUrl;
        }

        return $urls;
    }

    /**
     * Get media URLs with per-conversion dimensions.
     * Each conversion returns {url, width, height}.
     */
    public function getMediaUrlsDetailed(string $collection): ?array
    {
        if (! $this->hasMedia($collection)) {
            return null;
        }

        $media = $this->getFirstMedia($collection);
        $originalUrl = $media->getUrl();
        $originalWidth = $media->getCustomProperty('width');
        $originalHeight = $media->getCustomProperty('height');

        $urls = [
            'url' => $originalUrl,
            'original' => $originalUrl,
            'caption' => $media->getCustomProperty('caption'),
            'alt' => $media->getCustomProperty('alt') ?? $media->name,
            'width' => $originalWidth,
            'height' => $originalHeight,
        ];

        $targetWidths = $this->getConversionTargetWidths();

        foreach (['lqip', 'sm', 'md', 'lg', 'xl'] as $conversion) {
            $convUrl = $media->hasGeneratedConversion($conversion)
                ? $media->getUrl($conversion)
                : $originalUrl;

            $convWidth = $originalWidth;
            $convHeight = $originalHeight;

            if ($originalWidth && $originalHeight && isset($targetWidths[$conversion])) {
                $convWidth = min($targetWidths[$conversion], $originalWidth);
                $convHeight = (int) round($originalHeight * $convWidth / $originalWidth);
            }

            $urls[$conversion] = [
                'url' => $convUrl,
                'width' => $convWidth,
                'height' => $convHeight,
            ];
        }

        return $urls;
    }

    /**
     * Get target widths for each conversion name.
     * Override in models if conversions differ.
     */
    protected function getConversionTargetWidths(): array
    {
        return [
            'lqip' => 20,
            'sm' => 450,
            'md' => 900,
            'lg' => 1200,
            'xl' => 1500,
        ];
    }

    /**
     * Get available conversions for a collection
     */
    protected function getMediaConversions(string $collection): array
    {
        $conversionsConfig = $this->getMediaConversionsConfig();
        $conversions = [];

        foreach ($conversionsConfig as $conversionName => $config) {
            if (isset($config['collections']) && in_array($collection, $config['collections'])) {
                $conversions[] = $conversionName;
            }
        }

        return $conversions;
    }

    /**
     * Register media collections dynamically
     * Call this method in your model's registerMediaCollections method
     */
    protected function registerDynamicMediaCollections(): void
    {
        $collections = $this->getMediaCollections();

        foreach ($collections as $collection => $config) {
            $mediaCollection = $this->addMediaCollection($collection);

            if ($config['single_file'] ?? true) {
                $mediaCollection->singleFile();
            }

            if (isset($config['mime_types'])) {
                $mediaCollection->acceptsMimeTypes($config['mime_types']);
            }
        }
    }

    /**
     * Register media conversions dynamically
     * Call this method in your model's registerMediaConversions method
     */
    protected function registerDynamicMediaConversions(): void
    {
        $conversions = $this->getMediaConversionsConfig();

        foreach ($conversions as $conversionName => $config) {
            $conversion = $this->addMediaConversion($conversionName)
                ->width($config['width'])
                ->height($config['height'])
                ->quality($config['quality']);

            if (isset($config['collections'])) {
                $conversion->performOnCollections($config['collections']);
            }
        }
    }

    /**
     * Define media collections for the model
     * Override this method in your models
     */
    protected function getMediaCollections(): array
    {
        return [];
    }

    /**
     * Define media conversions for the model
     * Override this method in your models
     */
    protected function getMediaConversionsConfig(): array
    {
        return [];
    }
}
