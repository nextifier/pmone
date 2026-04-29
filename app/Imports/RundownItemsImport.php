<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportProgress;
use App\Models\RundownItem;
use Carbon\Carbon;
use Illuminate\Support\Arr;
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

class RundownItemsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithEvents, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use Concerns\ImportsFirstSheetOnly, Importable, TracksImportProgress;

    /**
     * Translatable fields exported as `<field>_en` and `<field>_id` columns.
     */
    private const TRANSLATABLE_FIELDS = [
        'title',
        'subtitle',
        'description',
        'theme',
        'location',
        'presented_by',
        'moderator',
    ];

    protected array $failures = [];

    protected int $importedCount = 0;

    public function __construct(
        protected int $eventId,
    ) {}

    public function prepareForValidation($data, $index)
    {
        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $en = isset($data[$field.'_en']) ? trim((string) $data[$field.'_en']) : '';
            $id = isset($data[$field.'_id']) ? trim((string) $data[$field.'_id']) : '';

            $value = [];
            if ($en !== '') {
                $value['en'] = $en;
            }
            if ($id !== '') {
                $value['id'] = $id;
            }

            $data[$field] = $value;
        }

        if (! empty($data['date'])) {
            try {
                $data['date'] = Carbon::parse($data['date'])->format('Y-m-d');
            } catch (\Throwable $e) {
                // Leave as-is so validation rule reports the error.
            }
        }

        foreach (['start_time', 'end_time'] as $timeField) {
            if (! empty($data[$timeField])) {
                $data[$timeField] = $this->normalizeTime((string) $data[$timeField]);
            }
        }

        $data['panelists'] = $this->decodeJsonArray(Arr::get($data, 'panelists_json'));
        $data['speakers'] = $this->decodeJsonArray(Arr::get($data, 'speakers_json'));

        $data['categories'] = $this->parseCategories(Arr::get($data, 'categories'));

        $data['is_active'] = $this->parseBoolean(Arr::get($data, 'active', true));

        return $data;
    }

    public function model(array $row): ?RundownItem
    {
        $item = RundownItem::create([
            'event_id' => $this->eventId,
            'date' => $row['date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'title' => $row['title'],
            'subtitle' => ! empty($row['subtitle']) ? $row['subtitle'] : null,
            'description' => ! empty($row['description']) ? $row['description'] : null,
            'theme' => ! empty($row['theme']) ? $row['theme'] : null,
            'location' => ! empty($row['location']) ? $row['location'] : null,
            'presented_by' => ! empty($row['presented_by']) ? $row['presented_by'] : null,
            'moderator' => ! empty($row['moderator']) ? $row['moderator'] : null,
            'panelists' => ! empty($row['panelists']) ? $row['panelists'] : null,
            'speakers' => ! empty($row['speakers']) ? $row['speakers'] : null,
            'is_active' => $row['is_active'] ?? true,
        ]);

        if (! empty($row['categories'])) {
            $item->syncTagsWithType($row['categories'], 'rundown_category');
        }

        $this->importedCount++;
        $this->updateProgress($this->importedCount);

        return $item;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn (BeforeImport $event) => $this->initProgressTracking($event),
        ];
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],

            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:500'],
            'title.id' => ['nullable', 'string', 'max:500'],

            'subtitle' => ['nullable', 'array'],
            'subtitle.en' => ['nullable', 'string', 'max:500'],
            'subtitle.id' => ['nullable', 'string', 'max:500'],

            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.id' => ['nullable', 'string'],

            'theme' => ['nullable', 'array'],
            'theme.en' => ['nullable', 'string', 'max:500'],
            'theme.id' => ['nullable', 'string', 'max:500'],

            'location' => ['nullable', 'array'],
            'location.en' => ['nullable', 'string', 'max:500'],
            'location.id' => ['nullable', 'string', 'max:500'],

            'presented_by' => ['nullable', 'array'],
            'presented_by.en' => ['nullable', 'string', 'max:255'],
            'presented_by.id' => ['nullable', 'string', 'max:255'],

            'moderator' => ['nullable', 'array'],
            'moderator.en' => ['nullable', 'string', 'max:255'],
            'moderator.id' => ['nullable', 'string', 'max:255'],

            'panelists' => ['nullable', 'array'],
            'panelists.*.name' => ['required_with:panelists', 'string', 'max:255'],
            'panelists.*.title' => ['nullable', 'string', 'max:255'],

            'speakers' => ['nullable', 'array'],
            'speakers.*.name' => ['required_with:speakers', 'string', 'max:255'],
            'speakers.*.title' => ['nullable', 'string', 'max:255'],
            'speakers.*.organization' => ['nullable', 'string', 'max:255'],

            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:100'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'title.en.required' => 'English title (Title (EN)) is required.',
            'end_time.after_or_equal' => 'End time must be after or equal to start time.',
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

    private function decodeJsonArray(mixed $value): ?array
    {
        if (is_array($value)) {
            $flattened = $this->flattenLocalizedList($value);

            return $flattened ?: null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode(trim($value), true);

        if (! is_array($decoded)) {
            return null;
        }

        $flattened = $this->flattenLocalizedList($decoded);

        return $flattened ?: null;
    }

    private function flattenLocalizedList(array $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return $value;
        }

        foreach (['en', 'id'] as $locale) {
            if (isset($value[$locale]) && is_array($value[$locale]) && ! empty($value[$locale])) {
                return array_values($value[$locale]);
            }
        }

        foreach ($value as $candidate) {
            if (is_array($candidate) && ! empty($candidate)) {
                return array_values($candidate);
            }
        }

        return [];
    }

    private function parseCategories(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $value));

        return array_values(array_filter($items));
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'yes', 'y', 'true', 'active', 'visible', 'on'], true);
        }

        return true;
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
