<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Conversions\FileManipulator;

class SafeMediaConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $maxExceptions = 3;

    public function __construct(
        public Media $media,
        public array $conversions = []
    ) {}

    public function handle(FileManipulator $fileManipulator): void
    {
        try {
            // Verify media exists before processing
            if (!$this->verifyMediaExists()) {
                return;
            }

            // Log conversion start
            Log::info('MediaLibrary: Starting conversion', [
                'media_id' => $this->media->id,
                'file' => $this->media->file_name,
                'conversions' => count($this->conversions),
            ]);

            // Perform conversions with error handling
            $fileManipulator->performConversions(
                collect($this->conversions),
                $this->media
            );

            Log::info('MediaLibrary: Conversion completed', [
                'media_id' => $this->media->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('MediaLibrary: Conversion failed', [
                'media_id' => $this->media->id,
                'file' => $this->media->file_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }

    private function verifyMediaExists(): bool
    {
        // Check if media record exists in database
        if (!$this->media->exists) {
            Log::warning('MediaLibrary: Media record not found', [
                'media_id' => $this->media->id,
            ]);
            return false;
        }

        // Check if physical file exists
        if (!$this->media->exists()) {
            Log::error('MediaLibrary: Physical file not found', [
                'media_id' => $this->media->id,
                'path' => $this->media->getPath(),
                'disk' => $this->media->disk,
            ]);

            // Try to find file in different locations
            $this->attemptFileRecovery();

            return false;
        }

        return true;
    }

    private function attemptFileRecovery(): void
    {
        Log::info('MediaLibrary: Attempting file recovery', [
            'media_id' => $this->media->id,
        ]);

        $possiblePaths = [
            $this->media->getPath(), // Original path
            $this->media->getPathRelativeToRoot(), // Relative path
            "media/{$this->media->id}/{$this->media->file_name}", // Alternative structure
        ];

        $disk = Storage::disk($this->media->disk);

        foreach ($possiblePaths as $path) {
            if ($disk->exists($path)) {
                Log::info('MediaLibrary: File found in alternative location', [
                    'media_id' => $this->media->id,
                    'original_path' => $this->media->getPath(),
                    'found_path' => $path,
                ]);
                break;
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MediaLibrary: Conversion job failed permanently', [
            'media_id' => $this->media->id,
            'file' => $this->media->file_name,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}