<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryDiagnosticsCommand extends Command
{
    protected $signature = 'media:diagnose
                            {--media-id= : Check specific media ID}
                            {--check-binaries : Check image processing binaries}
                            {--check-storage : Check storage configuration}';

    protected $description = 'Diagnose MediaLibrary issues in production';

    public function handle(): int
    {
        $this->info('🔍 MediaLibrary Diagnostics');
        $this->newLine();

        if ($this->option('check-binaries')) {
            $this->checkBinaries();
        }

        if ($this->option('check-storage')) {
            $this->checkStorage();
        }

        $mediaId = $this->option('media-id');
        if ($mediaId) {
            $this->checkSpecificMedia($mediaId);
        } else {
            $this->checkRecentMedia();
        }

        return self::SUCCESS;
    }

    private function checkBinaries(): void
    {
        $this->info('📦 Checking Image Processing Binaries...');

        $binaries = [
            'jpegoptim' => 'jpegoptim',
            'pngquant' => 'pngquant',
            'optipng' => 'optipng',
            'gifsicle' => 'gifsicle',
            'cwebp' => 'cwebp',
            'avifenc' => 'avifenc',
            'imagemagick' => 'convert', // ImageMagick
        ];

        foreach ($binaries as $name => $binary) {
            $result = shell_exec("which $binary 2>/dev/null");
            if ($result) {
                $this->line("✅ $name: " . trim($result));
            } else {
                $this->line("❌ $name: Not found");
            }
        }

        // Check PHP extensions
        $this->info('🔧 Checking PHP Extensions...');
        $extensions = ['gd', 'imagick', 'exif'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                $this->line("✅ $ext extension: Loaded");
            } else {
                $this->line("❌ $ext extension: Not loaded");
            }
        }

        $this->newLine();
    }

    private function checkStorage(): void
    {
        $this->info('💾 Checking Storage Configuration...');

        $mediaDisk = config('media-library.disk_name');
        $this->line("Media Disk: $mediaDisk");

        try {
            $disk = Storage::disk($mediaDisk);
            $this->line("✅ Storage disk '$mediaDisk' is accessible");

            // Check if we can write
            $testFile = 'test-' . time() . '.txt';
            $disk->put($testFile, 'test');
            if ($disk->exists($testFile)) {
                $this->line("✅ Can write to disk");
                $disk->delete($testFile);
            } else {
                $this->line("❌ Cannot write to disk");
            }
        } catch (\Exception $e) {
            $this->line("❌ Storage disk error: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function checkSpecificMedia(int $mediaId): void
    {
        $this->info("🔎 Checking Media ID: $mediaId");

        $media = Media::find($mediaId);
        if (!$media) {
            $this->error("Media not found!");
            return;
        }

        $this->line("File: " . $media->file_name);
        $this->line("Collection: " . $media->collection_name);
        $this->line("Disk: " . $media->disk);
        $this->line("Path: " . $media->getPath());

        // Check if original file exists
        if ($media->exists()) {
            $this->line("✅ Original file exists");
        } else {
            $this->line("❌ Original file missing: " . $media->getPath());
        }

        // Check conversions
        $conversions = $media->getMediaConversions();
        $this->line("Expected conversions: " . $conversions->count());

        foreach ($conversions as $conversion) {
            $conversionPath = $media->getPath($conversion->name);
            if (file_exists($conversionPath) || Storage::disk($media->disk)->exists($media->getPathRelativeToRoot($conversion->name))) {
                $this->line("✅ Conversion '{$conversion->name}' exists");
            } else {
                $this->line("❌ Conversion '{$conversion->name}' missing");
            }
        }

        $this->newLine();
    }

    private function checkRecentMedia(): void
    {
        $this->info('📋 Checking Recent Media Files...');

        $recentMedia = Media::latest()->take(5)->get();

        if ($recentMedia->isEmpty()) {
            $this->line("No media files found");
            return;
        }

        foreach ($recentMedia as $media) {
            $exists = $media->exists() ? '✅' : '❌';
            $this->line("$exists ID: {$media->id} - {$media->file_name} ({$media->collection_name})");

            if (!$media->exists()) {
                $this->line("   Missing path: " . $media->getPath());
            }
        }

        $this->newLine();
    }
}