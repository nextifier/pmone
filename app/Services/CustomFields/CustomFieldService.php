<?php

namespace App\Services\CustomFields;

use App\Models\CustomField;
use App\Support\FormFieldTypes;
use App\Support\PredefinedCustomFields;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\ResponseCache\Facades\ResponseCache;

class CustomFieldService
{
    /**
     * @return Collection<int, CustomField>
     */
    public function list(Model $owner, string $context): Collection
    {
        return $this->query($owner, $context)->ordered()->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Model $owner, string $context, array $data): CustomField
    {
        $data['context'] = $context;
        $data['options'] = $this->normalizedOptions($data);

        if ($context === CustomField::CONTEXT_BRAND && empty($data['key'])) {
            $data['key'] = $this->uniqueBrandKey($owner, $data['label'] ?? []);
        }

        return $owner->morphMany(CustomField::class, 'fieldable')->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomField $field, array $data): CustomField
    {
        // The brand storage key is immutable after create: regenerating it on
        // label edits would orphan every value stored under the old key.
        unset($data['key'], $data['system_key'], $data['context']);

        if ($field->system_key !== null) {
            // Predefined rows keep their catalog type; everything else
            // (label, options, validation, active state) stays editable.
            unset($data['type']);
        }

        if (array_key_exists('options', $data)) {
            $data['options'] = $this->normalizedOptions($data);
        }

        $field->update($data);

        return $field;
    }

    public function delete(CustomField $field): void
    {
        if ($field->system_key !== null && $field->context !== CustomField::CONTEXT_DOCUMENT) {
            throw ValidationException::withMessages([
                'field' => 'Predefined fields cannot be deleted. Use the toggle to disable them instead.',
            ]);
        }

        $field->delete();
    }

    /**
     * @param  array<int, array{id: int, order: int}>  $orders
     */
    public function reorder(Model $owner, string $context, array $orders): void
    {
        $ids = array_column($orders, 'id');

        if ($this->query($owner, $context)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'orders' => 'One or more fields do not belong to this owner.',
            ]);
        }

        DB::transaction(function () use ($owner, $context, $orders) {
            foreach ($orders as $order) {
                $this->query($owner, $context)
                    ->where('id', $order['id'])
                    ->update(['order_column' => $order['order']]);
            }
        });

        // Bulk query-builder updates skip model events, so bust the cache manually.
        $tags = (new CustomField(['context' => $context]))->responseCacheTagsForContext();

        if ($tags !== []) {
            ResponseCache::clear($tags);
        }
    }

    /**
     * Enable/disable a field from the PredefinedCustomFields library. Enabling
     * instantiates (or reactivates) a normal row with the catalog's labels and
     * options copied in; disabling only flips is_active so stored answers stay
     * resolvable and re-enabling reuses the same row + ulid.
     */
    public function togglePredefined(Model $owner, string $context, string $systemKey, bool $enabled): CustomField
    {
        $attributes = PredefinedCustomFields::attributesFor($context, $systemKey);

        if ($attributes === []) {
            throw ValidationException::withMessages([
                'system_key' => 'Unknown predefined field.',
            ]);
        }

        $field = $this->query($owner, $context)->where('system_key', $systemKey)->first();

        if ($field === null) {
            return $owner->morphMany(CustomField::class, 'fieldable')->create($attributes);
        }

        $field->update(['is_active' => $enabled]);

        return $field;
    }

    /**
     * Library entries merged with their instantiated state for an owner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function predefinedStatus(Model $owner, string $context): array
    {
        $existing = $this->query($owner, $context)
            ->whereNotNull('system_key')
            ->get()
            ->keyBy('system_key');

        $entries = [];

        foreach (PredefinedCustomFields::catalog($context) as $systemKey => $definition) {
            /** @var CustomField|null $field */
            $field = $existing->get($systemKey);

            $entries[] = [
                'system_key' => $systemKey,
                'type' => $definition['type'],
                'label_translations' => $field?->getTranslations('label') ?? $definition['label'],
                'options' => $field?->options ?? $definition['options'],
                'settings' => $field?->settings ?? $definition['settings'],
                'enabled' => (bool) ($field?->is_active ?? false),
                'field_id' => $field?->id,
            ];
        }

        return $entries;
    }

    protected function query(Model $owner, string $context): MorphMany
    {
        return $owner->morphMany(CustomField::class, 'fieldable')->where('context', $context);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{value: string, label: mixed}>|null
     */
    protected function normalizedOptions(array $data): ?array
    {
        if (! isset($data['options']) || ! is_array($data['options'])) {
            return null;
        }

        return FormFieldTypes::normalizeOptions($data['options']);
    }

    /**
     * @param  array<string, mixed>|string  $label
     */
    protected function uniqueBrandKey(Model $owner, array|string $label): string
    {
        $english = is_array($label) ? ($label['en'] ?? reset($label)) : $label;
        $base = Str::snake(Str::ascii((string) $english)) ?: 'field';

        $candidate = $base;
        $suffix = 2;

        while ($this->query($owner, CustomField::CONTEXT_BRAND)->where('key', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }
}
