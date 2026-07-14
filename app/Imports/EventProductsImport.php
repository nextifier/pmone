<?php

namespace App\Imports;

use App\Enums\BoothType;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class EventProductsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use Concerns\ImportsFirstSheetOnly, Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    public function __construct(
        protected int $eventId,
    ) {}

    public function prepareForValidation($data, $index): array
    {
        if (isset($data['category']) && ! is_null($data['category'])) {
            $data['category'] = trim($data['category']);
        }

        if (isset($data['unit']) && ! is_null($data['unit'])) {
            $data['unit'] = strtolower(trim($data['unit']));
        }

        // Accept both the "Price" and "Price (IDR)" heading (slugged to price_idr),
        // so a re-imported export file resolves the IDR price either way.
        $data['price'] ??= $data['price_idr'] ?? null;

        if (isset($data['price']) && ! is_null($data['price'])) {
            $data['price'] = (string) $data['price'];
        }

        if (isset($data['price_usd']) && ! is_null($data['price_usd'])) {
            $data['price_usd'] = (string) $data['price_usd'];
        }

        return $data;
    }

    public function model(array $row): ?EventProduct
    {
        $boothTypes = $this->resolveBoothTypes($row['booth_types'] ?? null);
        $isActive = $this->resolveActive($row['active'] ?? null);
        $categoryId = $this->resolveCategoryId(trim($row['category']));

        $product = EventProduct::create([
            'event_id' => $this->eventId,
            'category_id' => $categoryId,
            'name' => trim($row['name']),
            'description' => ! empty($row['description']) ? trim($row['description']) : null,
            'price' => (float) $row['price'],
            'price_usd' => isset($row['price_usd']) && $row['price_usd'] !== null && $row['price_usd'] !== ''
                ? (float) $row['price_usd']
                : null,
            'unit' => ! empty($row['unit']) ? strtolower(trim($row['unit'])) : 'unit',
            'booth_types' => $boothTypes,
            'is_active' => $isActive,
        ]);

        $this->importedCount++;

        return $product;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_usd' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'booth_types' => ['nullable', 'string'],
            'active' => ['nullable', 'string'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'category.required' => 'Category is required.',
            'name.required' => 'Product name is required.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'price_usd.numeric' => 'Price (USD) must be a number.',
            'price_usd.min' => 'Price (USD) cannot be negative.',
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

    private function resolveCategoryId(string $categoryTitle): int
    {
        $category = EventProductCategory::query()
            ->where('event_id', $this->eventId)
            ->where('title', $categoryTitle)
            ->first();

        if ($category) {
            return $category->id;
        }

        return EventProductCategory::create([
            'event_id' => $this->eventId,
            'title' => $categoryTitle,
        ])->id;
    }

    private function resolveBoothTypes(?string $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        $parts = preg_split('/[,;]+/', $value);
        $types = [];

        foreach ($parts as $part) {
            $type = BoothType::tryFromLabel($part)?->value;

            if ($type && ! in_array($type, $types)) {
                $types[] = $type;
            }
        }

        return count($types) > 0 ? $types : null;
    }

    private function resolveActive(?string $value): bool
    {
        if (empty($value)) {
            return true;
        }

        $normalized = strtolower(trim($value));

        return ! in_array($normalized, ['no', 'false', '0', 'inactive']);
    }
}
