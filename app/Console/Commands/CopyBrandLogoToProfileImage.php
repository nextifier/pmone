<?php

namespace App\Console\Commands;

use App\Models\Brand;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;

class CopyBrandLogoToProfileImage extends Command
{
    protected $signature = 'brands:copy-logo-to-profile-image
        {--dry-run : Report what would be copied without writing anything}
        {--force : Apply without the confirmation prompt}
        {--chunk=100 : Rows per chunk}';

    protected $description = 'Duplicate existing brand_logo media into the profile_image (avatar) collection. Idempotent: skips brands that already have a profile_image and non-image logos.';

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

    private int $scanned = 0;

    private int $copied = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('This copies existing brand_logo media into profile_image. Continue?')) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        $query = Media::query()
            ->where('model_type', Brand::class)
            ->where('collection_name', 'brand_logo');

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Scanning '.$query->count().' brand_logo media...');

        $query->chunkById((int) $this->option('chunk'), function ($chunk) use ($dryRun) {
            foreach ($chunk as $media) {
                $this->process($media, $dryRun);
            }
        });

        $this->newLine();
        $this->table(['Scanned', ($dryRun ? 'Would copy' : 'Copied'), 'Skipped'], [[
            $this->scanned,
            $this->copied,
            $this->skipped,
        ]]);

        if (! $dryRun && $this->copied > 0) {
            ResponseCache::clear(['brands']);
            $this->comment('Cleared brands response cache. Ensure a queue worker is running to generate conversions.');
        }

        return self::SUCCESS;
    }

    private function process(Media $media, bool $dryRun): void
    {
        $this->scanned++;

        // Only raster/vector images become avatars; PDF/AI/ZIP master files stay
        // in brand_logo only.
        if (! in_array($media->mime_type, self::IMAGE_MIMES, true)) {
            $this->skipped++;

            return;
        }

        $brand = $media->model;

        if (! $brand instanceof Brand) {
            $this->skipped++;

            return;
        }

        // Idempotent: never overwrite an existing avatar.
        if ($brand->hasMedia('profile_image')) {
            $this->skipped++;

            return;
        }

        if ($dryRun) {
            $this->copied++;

            return;
        }

        try {
            $media->copy($brand, 'profile_image');
            $this->copied++;
        } catch (\Throwable $e) {
            $this->skipped++;
            $this->warn("  ! Brand #{$brand->id} media #{$media->id}: ".$e->getMessage());
        }
    }
}
