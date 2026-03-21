<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BackfillMediaDimensions extends Command
{
    protected $signature = 'media:backfill-dimensions
        {--collection= : Only process specific collection}
        {--dry-run : Preview changes without saving}';

    protected $description = 'Backfill width and height into custom_properties for existing media records';

    public function handle(): int
    {
        $query = Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->where('mime_type', '!=', 'image/svg+xml');

        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
        }

        // Filter media that don't have width/height yet
        $query->where(function ($q) {
            $q->whereNull('custom_properties->width')
                ->orWhereNull('custom_properties->height');
        });

        $total = $query->count();
        $this->info("Found {$total} media records to process.");

        if ($total === 0) {
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById(50, function ($mediaItems) use (&$updated, &$skipped, &$failed, $bar) {
            foreach ($mediaItems as $media) {
                try {
                    $dimensions = $this->getDimensions($media);

                    if (! $dimensions) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    if (! $this->option('dry-run')) {
                        $media->setCustomProperty('width', $dimensions[0]);
                        $media->setCustomProperty('height', $dimensions[1]);
                        $media->saveQuietly();
                    }

                    $updated++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("  Failed media #{$media->id} ({$media->file_name}): {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $prefix = $this->option('dry-run') ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Updated: {$updated}");
        $this->comment("{$prefix}Skipped: {$skipped}");

        if ($failed > 0) {
            $this->warn("{$prefix}Failed: {$failed}");
        }

        $this->info('Done!');

        return Command::SUCCESS;
    }

    private function getDimensions(Media $media): ?array
    {
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        if (! $disk->exists($path)) {
            return null;
        }

        // For local/public disk, use the full path directly
        if (in_array($media->disk, ['local', 'public'])) {
            $fullPath = $disk->path($path);
            $info = @getimagesize($fullPath);

            return $info ? [$info[0], $info[1]] : null;
        }

        // For remote disks (R2/S3), download to temp file
        $contents = $disk->get($path);

        if (! $contents) {
            return null;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'media_dim_');
        file_put_contents($tmpFile, $contents);
        $info = @getimagesize($tmpFile);
        unlink($tmpFile);

        return $info ? [$info[0], $info[1]] : null;
    }
}
