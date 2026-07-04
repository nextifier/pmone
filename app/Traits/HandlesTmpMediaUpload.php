<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

trait HandlesTmpMediaUpload
{
    protected function moveTempToMediaCollection(
        HasMedia $model,
        string $tmpFolder,
        string $collection,
        bool $clearFirst = true,
        ?\Closure $beforeAdd = null,
    ): void {
        if (! Str::startsWith($tmpFolder, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$tmpFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpFolder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        if ($clearFirst) {
            $model->clearMediaCollection($collection);
        }

        if ($beforeAdd !== null) {
            $beforeAdd(Storage::disk('local')->path($filePath));
        }

        $model->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");
    }
}
