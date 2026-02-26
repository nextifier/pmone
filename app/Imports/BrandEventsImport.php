<?php

namespace App\Imports;

use App\Enums\BoothType;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class BrandEventsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

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
        // Normalize status to lowercase
        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        // Normalize booth type to lowercase
        if (isset($data['booth_type']) && ! is_null($data['booth_type'])) {
            $data['booth_type'] = strtolower(trim($data['booth_type']));
        }

        // Normalize phone to string
        if (isset($data['company_phone']) && ! is_null($data['company_phone'])) {
            $data['company_phone'] = (string) $data['company_phone'];
        }

        // Normalize booth size to string
        if (isset($data['booth_size_sqm']) && ! is_null($data['booth_size_sqm'])) {
            $data['booth_size_sqm'] = (string) $data['booth_size_sqm'];
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
                ]);
            }

            $this->brandCache[$brandNameLower] = $brand->id;
        }

        // Skip if brand already exists in this event
        $existingBrandEvent = BrandEvent::where('brand_id', $brand->id)
            ->where('event_id', $this->eventId)
            ->first();

        if ($existingBrandEvent) {
            $this->skippedCount++;

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
