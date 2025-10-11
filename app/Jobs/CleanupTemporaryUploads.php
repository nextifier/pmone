<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class CleanupTemporaryUploads implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tmpPath = 'tmp/uploads';

        if (! Storage::disk('local')->exists($tmpPath)) {
            return;
        }

        $directories = Storage::disk('local')->directories($tmpPath);

        foreach ($directories as $directory) {
            $metadataPath = "{$directory}/metadata.json";

            if (! Storage::disk('local')->exists($metadataPath)) {
                // Delete directories without metadata (corrupted)
                Storage::disk('local')->deleteDirectory($directory);

                continue;
            }

            $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);

            // Delete files older than 1 hour
            if (isset($metadata['uploaded_at'])) {
                $uploadedAt = \Carbon\Carbon::parse($metadata['uploaded_at']);
                if ($uploadedAt->diffInHours(now()) > 1) {
                    Storage::disk('local')->deleteDirectory($directory);
                }
            }
        }
    }
}
