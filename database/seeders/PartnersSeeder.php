<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Seeds global partners + logos, migrated from the per-app `partners.js` stores in
 * the pmone-events monorepo (deduped by name across all events).
 *
 * Data source: `database/seeders/data/partners.json` ({slug, name, website_url, logo})
 * plus logos in `database/seeders/partner-logos/` (committed to git), so production
 * seeding works from the repo with no external CDN — same approach as hotel-photos /
 * gallery-photos. Logos are small (<=600px), so no resizing is needed; the model's
 * `sm` (240px) conversion is generated on attach, SVG falls back to the original.
 *
 * Idempotent: matches partners by slug; only attaches a logo when the partner has none.
 *
 * Run with: php artisan db:seed --class=PartnersSeeder
 */
class PartnersSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/data/partners.json');
        $logosDir = database_path('seeders/partner-logos');

        if (! is_file($dataPath)) {
            $this->command?->error("Data file not found: {$dataPath}");

            return;
        }

        /** @var array<int, array{slug: string, name: string, website_url: ?string, logo: ?string}> $partners */
        $partners = json_decode(file_get_contents($dataPath), true);

        $created = 0;
        $renamed = 0;
        $logos = 0;

        foreach ($partners as $entry) {
            $slug = $entry['slug'] ?? null;
            $name = $entry['name'] ?? null;

            if (! $slug || ! $name) {
                continue;
            }

            $partner = Partner::withTrashed()->where('slug', $slug)->first();

            if (! $partner) {
                $partner = Partner::create([
                    'name' => $name,
                    'slug' => $slug,
                    'website_url' => $entry['website_url'] ?? null,
                    'status' => 'active',
                    'visibility' => 'public',
                ]);
                $created++;
            } elseif ($partner->name !== $name && $partner->name === ucwords(str_replace('-', ' ', $slug))) {
                // Existing partner still carries the auto-generated default name; sync the
                // curated name from the manifest. saveQuietly() keeps the slug and skips
                // activity logging; manually-renamed partners (name != default) are left alone.
                $partner->name = $name;
                $partner->saveQuietly();
                $renamed++;
            }

            $logoFile = $entry['logo'] ?? null;

            if ($logoFile && ! $partner->hasMedia('partner_logo')) {
                $path = "{$logosDir}/{$logoFile}";

                if (is_file($path)) {
                    $partner->addMedia($path)
                        ->preservingOriginal()
                        ->toMediaCollection('partner_logo');
                    $logos++;
                }
            }
        }

        if ($renamed > 0) {
            ResponseCache::clear(['partners']);
        }

        $this->command?->info("Partners: {$created} created, {$renamed} renamed, {$logos} logo(s) attached (".count($partners).' in manifest).');
    }
}
