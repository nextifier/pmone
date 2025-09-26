<?php

namespace App\Support\PathGenerator;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CollectionBasedPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive/';
    }

    protected function getBasePath(Media $media): string
    {
        // Handle temporary uploads (no model associated)
        if (empty($media->model_type) || empty($media->model_id)) {
            return "temp/{$media->collection_name}";
        }

        // Structure: {model_type}/{collection}/{model_id}
        $modelType = $this->getCleanModelType($media->model_type);

        return "{$modelType}/{$media->collection_name}/{$media->model_id}";
    }

    protected function getCleanModelType(string $modelType): string
    {
        // Convert "App\Models\User" to "users"
        $className = class_basename($modelType);

        return strtolower(str($className)->plural());
    }
}
