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
        $urls = [
            'url' => $media->getUrl(),
            'original' => $media->getUrl(),
            'lqip' => $media->getUrl('lqip'),
            'sm' => $media->getUrl('sm'),
            'md' => $media->getUrl('md'),
            'lg' => $media->getUrl('lg'),
            'xl' => $media->getUrl('xl'),
        ];

        return $urls;
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
