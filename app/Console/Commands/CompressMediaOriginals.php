<?php

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CompressMediaOriginals extends Command
{
    protected $signature = 'media:compress-originals
        {--dry-run : Report potential savings without writing anything}
        {--force : Apply without the confirmation prompt}
        {--disk= : Only process media stored on this disk}
        {--collection= : Only process this media collection}
        {--chunk=200 : Rows per chunk}';

    protected $description = 'Downscale + compress existing Media Library ORIGINALS that exceed the configured cap (reclaims disk). Idempotent.';

    private int $scanned = 0;

    private int $changed = 0;

    private int $skipped = 0;

    private int $bytesBefore = 0;

    private int $bytesAfter = 0;

    private int $max = 1920;

    private int $minBytes = 512000;

    public function handle(): int
    {
        $this->max = (int) config('images.original_max_dimension', 1920);
        $this->minBytes = (int) config('images.optimize_min_bytes', 512000);

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('This rewrites existing media original files. Continue?')) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        $query = Media::query()->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp']);
        if ($disk = $this->option('disk')) {
            $query->where('disk', $disk);
        }
        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Scanning '.$query->count().' image media...');

        $query->chunkById((int) $this->option('chunk'), function ($media) use ($dryRun) {
            foreach ($media as $item) {
                $this->process($item, $dryRun);
            }
        });

        $this->newLine();

        if ($dryRun) {
            $this->table(['Scanned', 'To compress', 'Already OK', 'Current size of candidates'], [[
                $this->scanned,
                $this->changed,
                $this->skipped,
                $this->humanBytes($this->bytesBefore),
            ]]);
            if ($this->changed > 0) {
                $this->comment('Estimate only. Run without --dry-run (add --force in production) to apply.');
            }

            return self::SUCCESS;
        }

        $saved = max(0, $this->bytesBefore - $this->bytesAfter);
        $this->table(['Scanned', 'Compressed', 'Skipped', 'Before', 'After', 'Reclaimed'], [[
            $this->scanned,
            $this->changed,
            $this->skipped,
            $this->humanBytes($this->bytesBefore),
            $this->humanBytes($this->bytesAfter),
            $this->humanBytes($saved),
        ]]);

        return self::SUCCESS;
    }

    private function process(Media $media, bool $dryRun): void
    {
        $this->scanned++;

        // Dry run: fast DB-only heuristic (no file I/O). The candidate condition
        // mirrors ImageOptimizer (downscale if over cap, OR re-compress if big).
        if ($dryRun) {
            $size = (int) $media->size;
            $width = (int) ($media->getCustomProperty('width') ?? 0);
            $height = (int) ($media->getCustomProperty('height') ?? 0);
            $overCap = $width > $this->max || $height > $this->max;

            if ($overCap || $size > $this->minBytes) {
                $this->changed++;
                $this->bytesBefore += $size;
            } else {
                $this->skipped++;
            }

            return;
        }

        try {
            $disk = Storage::disk($media->disk);
            $relative = $media->getPathRelativeToRoot();

            if (! $disk->exists($relative)) {
                $this->skipped++;

                return;
            }

            $before = (int) $disk->size($relative);

            // Temp file must keep the original extension so Spatie\Image can
            // infer the output format when saving.
            $ext = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
            $tmp = sys_get_temp_dir().'/mediaopt_'.uniqid().'.'.$ext;
            file_put_contents($tmp, $disk->get($relative));

            $changed = ImageOptimizer::compressInPlace($tmp);

            if (! $changed) {
                $this->skipped++;
                @unlink($tmp);

                return;
            }

            $after = (int) filesize($tmp);
            $this->bytesBefore += $before;
            $this->bytesAfter += $after;
            $this->changed++;

            if (! $dryRun) {
                $disk->put($relative, file_get_contents($tmp));

                $media->size = $after;
                if ($info = @getimagesize($tmp)) {
                    $media->setCustomProperty('width', $info[0]);
                    $media->setCustomProperty('height', $info[1]);
                }
                $media->save();
            }

            @unlink($tmp);
        } catch (\Throwable $e) {
            $this->skipped++;
            $this->warn("  ! Media #{$media->id}: ".$e->getMessage());
        }
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }
}
