<?php

namespace App\Imports;

use App\Enums\BoothType;
use App\Helpers\PhoneCountryHelper;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Validators\Failure;

class BrandEventsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithEvents, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use Concerns\ImportsFirstSheetOnly, Concerns\TracksImportProgress, Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    protected int $skippedCount = 0;

    /** @var array<string, int> Cache of brand name → brand_id to avoid duplicate lookups */
    protected array $brandCache = [];

    public function __construct(
        protected int $eventId,
    ) {}

    public function prepareForValidation($data, $index): array
    {
        // Trim email fields
        foreach (['company_email', 'pic_email'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        // Normalize status to lowercase
        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        // Normalize booth type to lowercase
        if (isset($data['booth_type']) && ! is_null($data['booth_type'])) {
            $data['booth_type'] = strtolower(trim($data['booth_type']));
        }

        // Normalize phone number to international format (+62...)
        if (isset($data['company_phone']) && ! is_null($data['company_phone'])) {
            $data['company_phone'] = PhoneCountryHelper::normalizePhoneNumber((string) $data['company_phone']);
        }

        // Normalize booth size to string
        if (isset($data['booth_size_sqm']) && ! is_null($data['booth_size_sqm'])) {
            $data['booth_size_sqm'] = (string) $data['booth_size_sqm'];
        }

        // Clean up and validate social media URLs - nullify invalid ones instead of failing the row
        $socialPatterns = [
            'instagram' => '/^https:\/\/(www\.)?instagram\.com\/.+/i',
            'tiktok' => '/^https:\/\/(www\.)?tiktok\.com\/.+/i',
            'facebook' => '/^https:\/\/(www\.)?(facebook\.com|fb\.com)\/.+/i',
            'x' => '/^https:\/\/(www\.)?(x\.com|twitter\.com)\/.+/i',
            'linkedin' => '/^https:\/\/(www\.)?linkedin\.com\/.+/i',
            'youtube' => '/^https:\/\/(www\.)?(youtube\.com|youtu\.be)\/.+/i',
        ];

        foreach (['instagram', 'tiktok', 'facebook', 'x', 'linkedin', 'youtube', 'website'] as $field) {
            if (! isset($data[$field]) || is_null($data[$field]) || trim($data[$field]) === '') {
                $data[$field] = null;

                continue;
            }

            $url = strtok(trim($data[$field]), '?');

            // Validate as URL
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                $data[$field] = null;

                continue;
            }

            // Validate platform-specific pattern
            if (isset($socialPatterns[$field]) && ! preg_match($socialPatterns[$field], $url)) {
                $data[$field] = null;

                continue;
            }

            $data[$field] = $url;
        }

        // Validate brand_logo as URL - nullify if invalid
        if (isset($data['brand_logo']) && ! is_null($data['brand_logo']) && trim($data['brand_logo']) !== '') {
            if (! filter_var(trim($data['brand_logo']), FILTER_VALIDATE_URL)) {
                $data['brand_logo'] = null;
            } else {
                $data['brand_logo'] = trim($data['brand_logo']);
            }
        }

        // Validate numeric custom fields - nullify if invalid
        if (isset($data['branch_total']) && ! is_null($data['branch_total'])) {
            $val = (int) $data['branch_total'];
            $data['branch_total'] = $val >= 0 ? (string) $val : null;
        }

        if (isset($data['establishment_year']) && ! is_null($data['establishment_year'])) {
            $val = (int) $data['establishment_year'];
            $data['establishment_year'] = ($val >= 1800 && $val <= (int) date('Y')) ? (string) $val : null;
        }

        return $data;
    }

    public function model(array $row): ?BrandEvent
    {
        $brandName = trim($row['brand_name']);
        $brandNameLower = strtolower($brandName);

        // Find or create brand (with cache)
        if (isset($this->brandCache[$brandNameLower])) {
            $brand = Brand::find($this->brandCache[$brandNameLower]);
        } else {
            $brand = Brand::whereRaw('LOWER(TRIM(name)) = ?', [$brandNameLower])->first();

            if (! $brand) {
                $brand = Brand::create([
                    'name' => $brandName,
                    'company_name' => ! empty($row['company_name']) ? trim($row['company_name']) : null,
                    'company_email' => ! empty($row['company_email']) ? trim($row['company_email']) : null,
                    'company_phone' => ! empty($row['company_phone']) ? trim($row['company_phone']) : null,
                    'description' => ! empty($row['description']) ? trim($row['description']) : null,
                    'custom_fields' => $this->buildCustomFields($row),
                ]);
            } else {
                // Update existing brand fields if not yet filled
                $updates = [];

                if (empty($brand->description) && ! empty($row['description'])) {
                    $updates['description'] = trim($row['description']);
                }

                $newCustomFields = $this->buildCustomFields($row);
                if ($newCustomFields) {
                    $existing = $brand->custom_fields ?? [];
                    foreach ($newCustomFields as $key => $value) {
                        if (empty($existing[$key])) {
                            $existing[$key] = $value;
                        }
                    }
                    $updates['custom_fields'] = $existing;
                }

                if (! empty($updates)) {
                    $brand->update($updates);
                }
            }

            $this->brandCache[$brandNameLower] = $brand->id;
        }

        // Import brand logo from URL if brand doesn't have one yet
        if (! empty($row['brand_logo']) && ! $brand->hasMedia('brand_logo')) {
            $this->importBrandLogo($brand, trim($row['brand_logo']));
        }

        // Create social links if brand doesn't have them yet
        $this->createLinks($brand, $row);

        // Sync business categories if provided
        if (! empty($row['business_categories'])) {
            $categories = array_map('trim', explode(',', $row['business_categories']));
            $categories = array_filter($categories);
            if (! empty($categories)) {
                $brand->syncBusinessCategories($categories);
            }
        }

        // Skip if brand already exists in this event
        $existingBrandEvent = BrandEvent::where('brand_id', $brand->id)
            ->where('event_id', $this->eventId)
            ->first();

        if ($existingBrandEvent) {
            $this->skippedCount++;
            $this->updateProgress($this->importedCount + $this->skippedCount);

            return null;
        }

        // Resolve booth type
        $boothType = $this->resolveBoothType($row['booth_type'] ?? null);

        // Resolve status
        $status = $this->resolveStatus($row['status'] ?? null);

        // Create brand-event record
        $brandEvent = BrandEvent::create([
            'brand_id' => $brand->id,
            'event_id' => $this->eventId,
            'booth_number' => ! empty($row['booth_number']) ? trim($row['booth_number']) : null,
            'booth_size' => ! empty($row['booth_size_sqm']) ? (float) $row['booth_size_sqm'] : null,
            'booth_type' => $boothType,
            'status' => $status,
        ]);

        // Process PIC email - find or create user and attach to brand
        if (! empty($row['pic_email'])) {
            $this->attachPicToBrand($brand, trim($row['pic_email']));
        }

        $this->importedCount++;
        $this->updateProgress($this->importedCount + $this->skippedCount);

        return $brandEvent;
    }

    public function rules(): array
    {
        return [
            'brand_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'pic_email' => ['nullable', 'email', 'max:255'],
            'booth_number' => ['nullable', 'string', 'max:50'],
            'booth_size_sqm' => ['nullable', 'numeric', 'min:0'],
            'booth_type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'linkedin' => ['nullable', 'string', 'max:500'],
            'youtube' => ['nullable', 'string', 'max:500'],
            'business_concept' => ['nullable', 'string', 'max:5000'],
            'investment_fee' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'brand_name.required' => 'Brand name is required.',
            'company_email.email' => 'Company email must be a valid email address.',
            'pic_email.email' => 'PIC email must be a valid email address.',
            'booth_size_sqm.numeric' => 'Booth size must be a number.',
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        $this->failures = array_merge($this->failures, $failures);
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn (BeforeImport $event) => $this->initProgressTracking($event),
        ];
    }

    private function resolveBoothType(?string $value): ?BoothType
    {
        if (empty($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match (true) {
            str_contains($normalized, 'raw') => BoothType::RawSpace,
            str_contains($normalized, 'enhanced') => BoothType::EnhancedShellScheme,
            str_contains($normalized, 'standard'), str_contains($normalized, 'shell') => BoothType::StandardShellScheme,
            default => null,
        };
    }

    private function resolveStatus(?string $value): string
    {
        if (empty($value)) {
            return 'active';
        }

        $normalized = strtolower(trim($value));

        return match (true) {
            in_array($normalized, ['active', 'confirmed', 'confirm', '1', 'yes']) => 'active',
            in_array($normalized, ['cancelled', 'cancel', 'canceled']) => 'cancelled',
            $normalized === 'draft' => 'draft',
            default => 'active',
        };
    }

    private function buildCustomFields(array $row): ?array
    {
        $customFields = [];

        if (! empty($row['branch_total'])) {
            $customFields['branch_total'] = (int) $row['branch_total'];
        }

        if (! empty($row['establishment_year'])) {
            $customFields['establishment_year'] = (int) $row['establishment_year'];
        }

        if (! empty($row['business_concept'])) {
            $customFields['business_concept'] = trim($row['business_concept']);
        }

        if (! empty($row['investment_fee'])) {
            $customFields['investment_fee'] = trim($row['investment_fee']);
        }

        return ! empty($customFields) ? $customFields : null;
    }

    private function importBrandLogo(Brand $brand, string $url): void
    {
        try {
            $brand->addMediaFromUrl($url)
                ->toMediaCollection('brand_logo');
        } catch (\Exception $e) {
            logger()->warning('Failed to import brand logo', [
                'brand_id' => $brand->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createLinks(Brand $brand, array $row): void
    {
        $linkMap = [
            'website' => 'Website',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'facebook' => 'Facebook',
            'x' => 'X',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
        ];

        $existingLabels = $brand->links()->pluck('label')->map(fn ($l) => strtolower($l))->toArray();
        $order = $brand->links()->max('order') ?? -1;

        foreach ($linkMap as $field => $label) {
            if (! empty($row[$field]) && ! in_array(strtolower($label), $existingLabels)) {
                $brand->links()->create([
                    'label' => $label,
                    'url' => trim($row[$field]),
                    'order' => ++$order,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function attachPicToBrand(Brand $brand, string $email): void
    {
        $email = strtolower(trim($email));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $password = Str::random(12);
            $user = User::create([
                'name' => Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
        }

        // Assign exhibitor role if doesn't have it
        if (! $user->hasRole('exhibitor')) {
            $user->assignRole('exhibitor');
        }

        // Attach to brand if not already
        if (! $brand->users()->where('user_id', $user->id)->exists()) {
            $brand->users()->attach($user->id);
        }
    }
}
