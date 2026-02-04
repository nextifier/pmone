<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedTempMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:cleanup-temp {--hours=24 : Delete files older than this many hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned temporary media files from uploads';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $expiryTime = Carbon::now()->subHours($hours);

        $this->info("Cleaning up temporary uploads older than {$hours} hours...");

        $disk = Storage::disk('local');
        $tempPath = 'tmp/uploads';

        if (! $disk->exists($tempPath)) {
            $this->info('No temporary uploads directory found.');

            return Command::SUCCESS;
        }

        $directories = $disk->directories($tempPath);
        $deletedCount = 0;
        $totalSize = 0;

        foreach ($directories as $directory) {
            // Check if directory starts with 'tmp-' (covers both 'tmp-' and 'tmp-media-')
            $folderName = basename($directory);
            if (! str_starts_with($folderName, 'tmp-')) {
                continue;
            }

            $metadataPath = "{$directory}/metadata.json";

            // Delete directories without metadata (corrupted)
            if (! $disk->exists($metadataPath)) {
                $this->line("Deleting corrupted directory (no metadata): {$folderName}");
                $disk->deleteDirectory($directory);
                $deletedCount++;

                continue;
            }

            // Read metadata
            $metadata = json_decode($disk->get($metadataPath), true);

            // Check if uploaded_at exists and is older than expiry time
            if (isset($metadata['uploaded_at'])) {
                $uploadedAt = Carbon::parse($metadata['uploaded_at']);

                if ($uploadedAt->lt($expiryTime)) {
                    // Calculate size before deletion
                    $files = $disk->allFiles($directory);
                    foreach ($files as $file) {
                        $totalSize += $disk->size($file);
                    }

                    // Delete the directory
                    $disk->deleteDirectory($directory);
                    $deletedCount++;

                    $this->line("Deleted: {$folderName} (uploaded: {$uploadedAt->diffForHumans()})");
                }
            }
        }

        $formattedSize = $this->formatBytes($totalSize);

        if ($deletedCount > 0) {
            $message = "Successfully deleted {$deletedCount} orphaned temporary upload(s), freed {$formattedSize}.";
            $this->info($message);
            Log::info('Temporary uploads cleanup completed', [
                'deleted_count' => $deletedCount,
                'freed_size' => $formattedSize,
                'hours_threshold' => $hours,
            ]);
        } else {
            $this->info('No orphaned temporary uploads found.');
            Log::debug('Temporary uploads cleanup: no expired files found', [
                'hours_threshold' => $hours,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 Bytes';
        }

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2).' '.$sizes[$i];
    }
}
