<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hotels:backfill-location {--dry-run : Preview changes without saving}')]
#[Description('Corrects city and province on seeded hotels to official administrative names')]
class BackfillHotelLocation extends Command
{
    /**
     * Correct city/province per seeded hotel slug. City uses the official
     * kota/kabupaten name so it matches the address dropdown options exactly.
     * Determined from each hotel's real street address.
     *
     * @var array<string, array{city: string, province: string}>
     */
    private const HOTEL_LOCATIONS = [
        'fairmont-jakarta' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'hotel-mulia-senayan' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'the-sultan-hotel-residence-jakarta' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'shangri-la-hotel-jakarta' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'the-ritz-carlton-pacific-place' => ['city' => 'Kota Jakarta Selatan', 'province' => 'DKI Jakarta'],
        'holiday-inn-express-jakarta-international-expo' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'b-hotel-jakarta' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'aston-inn-kemayoran' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'harris-hotel-kelapa-gading' => ['city' => 'Kota Jakarta Utara', 'province' => 'DKI Jakarta'],
        'pop-hotel-kemayoran' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'the-grove-suites-bsd-city' => ['city' => 'Kota Tangerang Selatan', 'province' => 'Banten'],
        'aryaduta-bsd' => ['city' => 'Kota Tangerang Selatan', 'province' => 'Banten'],
        'swiss-belhotel-serpong' => ['city' => 'Kota Tangerang Selatan', 'province' => 'Banten'],
        'mercure-serpong-alam-sutera' => ['city' => 'Kota Tangerang Selatan', 'province' => 'Banten'],
        'santika-premiere-bsd' => ['city' => 'Kota Tangerang Selatan', 'province' => 'Banten'],
        'pullman-jakarta-pik-avenue' => ['city' => 'Kota Jakarta Utara', 'province' => 'DKI Jakarta'],
        'swiss-belhotel-mangga-besar' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
        'aston-pluit-hotel-residence' => ['city' => 'Kota Jakarta Utara', 'province' => 'DKI Jakarta'],
        'mercure-convention-centre-ancol' => ['city' => 'Kota Jakarta Utara', 'province' => 'DKI Jakarta'],
        'holiday-inn-jakarta-pik' => ['city' => 'Kota Jakarta Pusat', 'province' => 'DKI Jakarta'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $updated = 0;
        $unchanged = 0;

        /** @var list<string> $unmapped */
        $unmapped = [];

        foreach (Hotel::withTrashed()->get() as $hotel) {
            $target = self::HOTEL_LOCATIONS[$hotel->slug] ?? null;

            if ($target === null) {
                $unmapped[] = "#{$hotel->id} {$hotel->name} (slug: {$hotel->slug})";

                continue;
            }

            $address = is_array($hotel->address) ? $hotel->address : [];
            $currentCity = $address['city'] ?? null;
            $currentProvince = $address['province'] ?? null;

            if ($currentCity === $target['city'] && $currentProvince === $target['province']) {
                $unchanged++;

                continue;
            }

            $this->line("  #{$hotel->id} {$hotel->name}: ".
                ($currentCity ?? 'NULL').' / '.($currentProvince ?? 'NULL').
                " → {$target['city']} / {$target['province']}");

            if (! $dryRun) {
                $address['city'] = $target['city'];
                $address['province'] = $target['province'];
                $hotel->address = $address;
                // save() (not saveQuietly) so the `saved` event fires and
                // ClearsResponseCache flushes the cached public hotel responses.
                $hotel->save();
            }

            $updated++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would update ' : 'Updated ')."{$updated} hotel(s).");
        $this->line("Already correct: {$unchanged}");

        if ($unmapped !== []) {
            $this->newLine();
            $this->warn('Not a seeded hotel — set location manually if needed ('.count($unmapped).'):');
            foreach ($unmapped as $line) {
                $this->line("  - {$line}");
            }
        }

        return self::SUCCESS;
    }
}
