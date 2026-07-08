<?php

namespace App\Support;

use App\Models\CustomField;
use Illuminate\Support\Str;

class FormFieldTypes
{
    public const OPTION_TYPES = [
        CustomField::TYPE_SELECT,
        CustomField::TYPE_MULTI_SELECT,
        CustomField::TYPE_CHECKBOX_GROUP,
        CustomField::TYPE_RADIO,
    ];

    public const MULTI_VALUE_TYPES = [
        CustomField::TYPE_MULTI_SELECT,
        CustomField::TYPE_CHECKBOX_GROUP,
        CustomField::TYPE_TAGS,
    ];

    /**
     * Analytics aggregation kind per type: options | numeric | text | none.
     *
     * @var array<string, string>
     */
    private const DEFINITIONS = [
        CustomField::TYPE_TEXT => 'text',
        CustomField::TYPE_TEXTAREA => 'text',
        CustomField::TYPE_EMAIL => 'text',
        CustomField::TYPE_NUMBER => 'numeric',
        CustomField::TYPE_PHONE => 'text',
        CustomField::TYPE_URL => 'text',
        CustomField::TYPE_RICH_TEXT => 'text',
        CustomField::TYPE_DATE => 'text',
        CustomField::TYPE_TIME => 'text',
        CustomField::TYPE_DATETIME => 'text',
        CustomField::TYPE_DATE_RANGE => 'text',
        CustomField::TYPE_SELECT => 'options',
        CustomField::TYPE_MULTI_SELECT => 'options',
        CustomField::TYPE_CHECKBOX => 'options',
        CustomField::TYPE_CHECKBOX_GROUP => 'options',
        CustomField::TYPE_RADIO => 'options',
        CustomField::TYPE_TAGS => 'options',
        CustomField::TYPE_SWITCH => 'options',
        CustomField::TYPE_COUNTRY => 'options',
        CustomField::TYPE_COLOR => 'options',
        CustomField::TYPE_FILE => 'text',
        CustomField::TYPE_RATING => 'numeric',
        CustomField::TYPE_LINEAR_SCALE => 'numeric',
        CustomField::TYPE_SLIDER => 'numeric',
        CustomField::TYPE_SECTION => 'none',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function isChoice(string $type): bool
    {
        return in_array($type, self::OPTION_TYPES, true);
    }

    public static function isInput(string $type): bool
    {
        return $type !== CustomField::TYPE_SECTION;
    }

    public static function analyticsKind(string $type): string
    {
        return self::DEFINITIONS[$type] ?? 'text';
    }

    /**
     * Build validation rules for a single CustomField, keyed by the full input path.
     *
     * @return array<string, array<int, string>>
     */
    public static function rulesFor(CustomField $field, string $key): array
    {
        $validation = $field->validation ?? [];
        $settings = $field->settings ?? [];

        return self::rulesForType(
            $field->type,
            $key,
            ! empty($validation['required']),
            self::optionValues($field),
            [
                'min' => $validation['min'] ?? null,
                'max' => $validation['max'] ?? null,
                'min_selections' => $validation['min_selections'] ?? null,
                'max_selections' => $validation['max_selections'] ?? null,
                'rating_max' => $settings['max'] ?? null,
                'scale_min' => $validation['min'] ?? null,
                'scale_max' => $validation['max'] ?? null,
                'file_multiple' => ! empty($settings['multiple']),
                'max_files' => $validation['max_files'] ?? null,
            ],
        );
    }

    /**
     * Build validation rules for a single field type, keyed by the full input
     * path. Decoupled from FormField so callers with a leaner field model (e.g.
     * business-matching intake) can reuse the same per-type
     * rules. Numeric/option constraints are passed via $opts; absent keys fall
     * back to the same defaults the Form Builder uses.
     *
     * @param  array<int, string>  $optionValues  Allowed values for choice types.
     * @param  array<string, mixed>  $opts  min, max, min_selections, max_selections, rating_max, scale_min, scale_max, file_multiple, max_files.
     * @return array<string, array<int, string>>
     */
    public static function rulesForType(
        string $type,
        string $key,
        bool $required,
        array $optionValues = [],
        array $opts = [],
    ): array {
        $base = [$required ? 'required' : 'nullable'];
        $rules = [];

        switch ($type) {
            case CustomField::TYPE_TEXT:
            case CustomField::TYPE_TEXTAREA:
            case CustomField::TYPE_RICH_TEXT:
                $base[] = 'string';
                if (isset($opts['min'])) {
                    $base[] = 'min:'.$opts['min'];
                }
                $base[] = 'max:'.($opts['max'] ?? 65535);
                break;

            case CustomField::TYPE_EMAIL:
                $base[] = 'string';
                $base[] = 'email';
                $base[] = 'max:255';
                break;

            case CustomField::TYPE_PHONE:
                $base[] = 'string';
                $base[] = 'max:30';
                break;

            case CustomField::TYPE_URL:
                $base[] = 'string';
                $base[] = 'url';
                $base[] = 'max:2048';
                break;

            case CustomField::TYPE_NUMBER:
            case CustomField::TYPE_SLIDER:
                $base[] = 'numeric';
                if (isset($opts['min'])) {
                    $base[] = 'min:'.$opts['min'];
                }
                if (isset($opts['max'])) {
                    $base[] = 'max:'.$opts['max'];
                }
                break;

            case CustomField::TYPE_DATE:
                $base[] = 'date_format:Y-m-d';
                break;

            case CustomField::TYPE_TIME:
                $base[] = 'date_format:H:i';
                break;

            case CustomField::TYPE_DATETIME:
                $base[] = 'date_format:Y-m-d H:i';
                break;

            case CustomField::TYPE_DATE_RANGE:
                $base[] = 'array';
                $rules[$key.'.start'] = ['required_with:'.$key, 'date_format:Y-m-d'];
                $rules[$key.'.end'] = ['required_with:'.$key, 'date_format:Y-m-d', 'after_or_equal:'.$key.'.start'];
                break;

            case CustomField::TYPE_SELECT:
            case CustomField::TYPE_RADIO:
                $base[] = 'string';
                if ($optionValues) {
                    $base[] = 'in:'.implode(',', $optionValues);
                }
                break;

            case CustomField::TYPE_MULTI_SELECT:
            case CustomField::TYPE_CHECKBOX_GROUP:
                $base[] = 'array';
                if (isset($opts['min_selections'])) {
                    $base[] = 'min:'.$opts['min_selections'];
                }
                if (isset($opts['max_selections'])) {
                    $base[] = 'max:'.$opts['max_selections'];
                }
                $itemRules = ['string'];
                if ($optionValues) {
                    $itemRules[] = 'in:'.implode(',', $optionValues);
                }
                $rules[$key.'.*'] = $itemRules;
                break;

            case CustomField::TYPE_TAGS:
                $base[] = 'array';
                if (isset($opts['max_selections'])) {
                    $base[] = 'max:'.$opts['max_selections'];
                }
                $rules[$key.'.*'] = ['string', 'max:100'];
                break;

            case CustomField::TYPE_CHECKBOX:
            case CustomField::TYPE_SWITCH:
                $base = $required ? ['accepted'] : ['nullable', 'boolean'];
                break;

            case CustomField::TYPE_FILE:
                if (! empty($opts['file_multiple'])) {
                    $base[] = 'array';
                    $base[] = 'max:'.($opts['max_files'] ?? 5);
                    $rules[$key.'.*'] = ['string', 'starts_with:form-'];
                } else {
                    $base[] = 'string';
                    $base[] = 'starts_with:form-';
                }
                break;

            case CustomField::TYPE_RATING:
                $base[] = 'integer';
                $base[] = 'min:1';
                $base[] = 'max:'.($opts['rating_max'] ?? 5);
                break;

            case CustomField::TYPE_LINEAR_SCALE:
                $base[] = 'integer';
                $base[] = 'min:'.($opts['scale_min'] ?? 1);
                $base[] = 'max:'.($opts['scale_max'] ?? 5);
                break;

            case CustomField::TYPE_COLOR:
                $base[] = 'string';
                $base[] = 'regex:/^#[0-9a-fA-F]{6}$/';
                break;

            case CustomField::TYPE_COUNTRY:
                $base[] = 'string';
                $base[] = 'max:100';
                break;

            case CustomField::TYPE_SECTION:
                return [];
        }

        return [$key => $base] + $rules;
    }

    /**
     * Format a stored CustomField response value for export, email, and display.
     */
    public static function formatValue(CustomField $field, mixed $value): string
    {
        return self::formatValueForType($field->type, $value, $field->options ?? []);
    }

    /**
     * Format a logical value for a field type. Decoupled from FormField so leaner
     * field models (e.g. business-matching intake) can reuse it.
     * `$options` may be plain strings or {value,label} pairs.
     *
     * @param  array<int, mixed>  $options
     */
    public static function formatValueForType(string $type, mixed $value, array $options = []): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '-';
        }

        switch ($type) {
            case CustomField::TYPE_SELECT:
            case CustomField::TYPE_RADIO:
                return self::optionLabelFrom($options, $value);

            case CustomField::TYPE_MULTI_SELECT:
            case CustomField::TYPE_CHECKBOX_GROUP:
                $values = is_array($value) ? $value : [$value];

                return implode(', ', array_map(fn ($v) => self::optionLabelFrom($options, $v), $values));

            case CustomField::TYPE_TAGS:
                return implode(', ', (array) $value);

            case CustomField::TYPE_CHECKBOX:
            case CustomField::TYPE_SWITCH:
                return $value ? 'Yes' : 'No';

            case CustomField::TYPE_DATE_RANGE:
                if (is_array($value)) {
                    return trim(($value['start'] ?? '').' - '.($value['end'] ?? ''), ' -') ?: '-';
                }

                return (string) $value;

            case CustomField::TYPE_RICH_TEXT:
                return Str::squish(strip_tags((string) $value)) ?: '-';

            case CustomField::TYPE_FILE:
                $paths = is_array($value) ? $value : [$value];

                return implode(', ', array_map(fn ($p) => basename((string) $p), $paths)) ?: '-';

            default:
                if (is_array($value)) {
                    return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value));
                }

                return (string) $value;
        }
    }

    /**
     * Format a value as stored by the business-matching intake, which wraps
     * scalar answers in a single-element array. Unwraps to the logical value
     * for the type before formatting.
     *
     * @param  array<int, mixed>  $options
     */
    public static function formatStoredValue(string $type, mixed $stored, array $options = []): string
    {
        return self::formatValueForType($type, self::normalizeStored($type, $stored), $options);
    }

    /**
     * Reverse the storage wrapping: scalar types are persisted as `[value]`,
     * multi-value types and date ranges keep their natural array/object shape.
     */
    public static function normalizeStored(string $type, mixed $stored): mixed
    {
        if ($type === CustomField::TYPE_DATE_RANGE) {
            return is_array($stored) ? $stored : null;
        }

        if (in_array($type, self::MULTI_VALUE_TYPES, true)) {
            if ($stored === null) {
                return [];
            }

            return is_array($stored) ? array_values($stored) : [$stored];
        }

        if (is_array($stored)) {
            return $stored[0] ?? null;
        }

        return $stored;
    }

    /**
     * Allowed answer values for a field's choice types. Resolves an options
     * preset (settings.options_preset) before falling back to stored options;
     * tolerates both `[{value,label}]` pairs and legacy plain-string lists.
     *
     * @return array<int, string>
     */
    public static function optionValues(CustomField $field): array
    {
        $preset = $field->settings['options_preset'] ?? null;

        if (is_string($preset) && $preset !== '') {
            return array_column(self::presetOptions($preset), 'value');
        }

        return array_column(self::normalizeOptions($field->options ?? []), 'value');
    }

    /**
     * Canonicalize an options list to `[{value, label}]`. Plain strings become
     * self-labelled pairs; existing pairs pass through untouched (labels may be
     * a plain string or a {locale: string} translation map). Idempotent.
     *
     * @param  array<int, mixed>|null  $options
     * @return array<int, array{value: string, label: mixed}>
     */
    public static function normalizeOptions(?array $options): array
    {
        $normalized = [];

        foreach ($options ?? [] as $option) {
            if (is_array($option)) {
                $value = $option['value'] ?? null;

                if (! is_scalar($value)) {
                    continue;
                }

                $normalized[] = ['value' => (string) $value, 'label' => $option['label'] ?? (string) $value];
            } elseif (is_scalar($option)) {
                $normalized[] = ['value' => (string) $option, 'label' => (string) $option];
            }
        }

        return $normalized;
    }

    /**
     * Generated option lists that never go stale (e.g. the years list rolls
     * over automatically at new year). Referenced via settings.options_preset.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function presetOptions(string $preset): array
    {
        return match ($preset) {
            'years' => array_map(
                fn (int $year) => ['value' => (string) $year, 'label' => (string) $year],
                range((int) now()->year, 1900, -1),
            ),
            default => [],
        };
    }

    /**
     * Resolve an option's display label from either a plain string list or a
     * list of {value,label} pairs. Labels stored as {locale: string} maps
     * (predefined-library fields) resolve against the current app locale with
     * an `en` fallback.
     *
     * @param  array<int, mixed>  $options
     */
    private static function optionLabelFrom(array $options, mixed $value): string
    {
        foreach ($options as $option) {
            if (is_array($option)) {
                if (($option['value'] ?? null) == $value) {
                    return self::localizedOptionLabel($option['label'] ?? $option['value'] ?? '');
                }
            } elseif ($option == $value) {
                return (string) $option;
            }
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }

    private static function localizedOptionLabel(mixed $label): string
    {
        if (is_array($label)) {
            $resolved = $label[app()->getLocale()] ?? $label['en'] ?? reset($label);

            return is_scalar($resolved) ? (string) $resolved : '';
        }

        return is_scalar($label) ? (string) $label : '';
    }
}
