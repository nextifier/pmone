<?php

namespace App\Imports;

use App\Enums\BoothType;
use App\Models\EventProduct;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class EventProductsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

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

        if (isset($data['price']) && ! is_null($data['price'])) {
            $data['price'] = (string) $data['price'];
        }

        return $data;
    }

    public function model(array $row): ?EventProduct
    {
        $boothTypes = $this->resolveBoothTypes($row['booth_types'] ?? null);
        $isActive = $this->resolveActive($row['active'] ?? null);

        $product = EventProduct::create([
            'event_id' => $this->eventId,
            'category' => trim($row['category']),
            'name' => trim($row['name']),
            'description' => ! empty($row['description']) ? trim($row['description']) : null,
            'price' => (float) $row['price'],
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

    private function resolveBoothTypes(?string $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        $parts = preg_split('/[,;]+/', $value);
        $types = [];

        foreach ($parts as $part) {
            $normalized = strtolower(trim($part));

            $type = match (true) {
                str_contains($normalized, 'raw') => BoothType::RawSpace->value,
                str_contains($normalized, 'enhanced') => BoothType::EnhancedShellScheme->value,
                str_contains($normalized, 'standard'), str_contains($normalized, 'shell') => BoothType::StandardShellScheme->value,
                default => null,
            };

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
