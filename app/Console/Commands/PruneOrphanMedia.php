<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Reclaims disk by removing orphaned media:
 *  - Phase A: orphaned media RECORDS (parent model gone) via Spatie's
 *    `media-library:clean --delete-orphaned` (SoftDelete-aware).
 *  - Phase B: orphaned FILES on disk that no live media record references
 *    (the part media-library:clean does NOT handle, because the custom
 *    CollectionBasedPathGenerator stores many media in one shared directory).
 *
 * Safe by design: only deletes files inside directories that actually hold
 * media, and only when no live media in that directory references them
 * (original by file_name; conversions/responsive by "{stem}-" / "{stem}___"
 * prefix). Never touches files outside known media roots.
 *
 * Dry-run by default. Add --force to actually delete (required in production).
 */
class PruneOrphanMedia extends Command
{
    protected $signature = 'media:prune-orphans
        {--force : Actually delete (without this it is a dry run)}
        {--dry-run : Report only, never delete (overrides --force)}
        {--disk= : Media disk to scan (defaults to media-library disk)}
        {--root= : Limit to a single top-level media folder, e.g. posts}
        {--skip-records : Skip the orphaned-record cleanup phase}';

    protected $description = 'Delete orphaned media files + records that no longer belong to any model (reclaims disk). Idempotent.';

    public function handle(): int
    {
        $apply = $this->option('force') && ! $this->option('dry-run');
        $diskName = $this->option('disk') ?: config('media-library.disk_name', 'public');
        $tag = $apply ? '' : '[DRY RUN] ';

        // ── Phase A: orphaned records (model gone) ──────────────────────
        if (! $this->option('skip-records')) {
            $this->info($tag.'Cleaning orphaned media records (model deleted)...');
            $this->call('media-library:clean', array_filter([
                '--delete-orphaned' => true,
                '--force' => $apply ?: null,
                '--dry-run' => $apply ? null : true,
            ]));
        }

        // ── Phase B: orphaned files (no live record references them) ─────
        $this->info($tag.'Scanning for orphaned files on disk "'.$diskName.'"...');
        $disk = Storage::disk($diskName);

        // Build map: media directory => [file_name => true], [name stem => true]
        $live = [];
        Media::query()
            ->select(['id', 'model_type', 'model_id', 'collection_name', 'file_name', 'disk'])
            ->cursor()
            ->each(function (Media $m) use (&$live, $diskName) {
                if ($m->disk !== $diskName) {
                    return;
                }
                $dir = dirname($m->getPathRelativeToRoot());
                $live[$dir]['files'][$m->file_name] = true;
                $live[$dir]['stems'][pathinfo($m->file_name, PATHINFO_FILENAME)] = true;
            });

        $roots = [];
        foreach (array_keys($live) as $dir) {
            $roots[explode('/', $dir)[0]] = true;
        }
        if ($only = $this->option('root')) {
            $roots = array_key_exists($only, $roots) ? [$only => true] : [];
        }

        $orphans = 0;
        $bytes = 0;
        $perRoot = [];

        foreach (array_keys($roots) as $root) {
            foreach ($disk->allFiles($root) as $path) {
                $base = basename($path);
                if ($base === '.DS_Store') {
                    continue;
                }

                $parent = dirname($path);
                $isDerivative = in_array(basename($parent), ['conversions', 'responsive'], true);
                $mediaDir = $isDerivative ? dirname($parent) : $parent;
                $entry = $live[$mediaDir] ?? null;

                $valid = false;
                if ($entry) {
                    if (! $isDerivative) {
                        $valid = isset($entry['files'][$base]);
                    } else {
                        foreach (array_keys($entry['stems']) as $stem) {
                            if (str_starts_with($base, $stem.'-') || str_starts_with($base, $stem.'___')) {
                                $valid = true;
                                break;
                            }
                        }
                    }
                }

                if ($valid) {
                    continue;
                }

                $orphans++;
                $size = (int) $disk->size($path);
                $bytes += $size;
                $perRoot[$root] = ($perRoot[$root] ?? 0) + $size;

                if ($apply) {
                    $disk->delete($path);
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Orphan files', $apply ? 'Deleted size' : 'Reclaimable size'],
            [[$orphans, $this->humanBytes($bytes)]],
        );
        if ($perRoot) {
            arsort($perRoot);
            foreach ($perRoot as $root => $size) {
                $this->line('  '.str_pad($root, 18).$this->humanBytes($size));
            }
        }
        if (! $apply && $orphans > 0) {
            $this->comment('Dry run. Re-run with --force to delete (review the list above first).');
        }

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }
}
