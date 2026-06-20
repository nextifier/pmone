<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class ScanSoundsSeeder extends Seeder
{
    private const SOUND_DIR = __DIR__.'/scan-sounds';

    /**
     * Seed the global scanner notification sounds (CC0 defaults from soundcn).
     *
     * Idempotent and non-destructive: a staff-uploaded sound is never overwritten,
     * so this is safe to re-run on every deploy.
     */
    public function run(): void
    {
        $setting = AppSetting::firstOrCreate(
            ['key' => 'scan_sounds'],
            ['value' => ['success_url' => null, 'failed_url' => null, 'enabled' => true]],
        );

        $value = $setting->value ?? [];
        $value['enabled'] = $value['enabled'] ?? true;

        foreach (['success' => 'scan_success_sound', 'failed' => 'scan_failed_sound'] as $field => $collection) {
            if ($setting->getFirstMedia($collection)) {
                $value["{$field}_url"] = $setting->getFirstMediaUrl($collection);

                continue;
            }

            $path = $this->defaultSoundPath($field);

            if ($path === null) {
                $this->command?->warn("Default scan {$field} sound missing in ".self::SOUND_DIR);

                continue;
            }

            // Use the returned Media's URL directly: the idempotency check above
            // pre-loaded an (empty) media relation, so getFirstMediaUrl() on the
            // same instance would read stale and return "".
            $media = $setting->addMedia($path)->preservingOriginal()->toMediaCollection($collection);
            $value["{$field}_url"] = $media->getUrl();
        }

        AppSetting::set('scan_sounds', $value);
    }

    private function defaultSoundPath(string $field): ?string
    {
        foreach (['mp3', 'ogg', 'wav', 'm4a', 'aac', 'webm'] as $ext) {
            $path = self::SOUND_DIR."/{$field}.{$ext}";

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
