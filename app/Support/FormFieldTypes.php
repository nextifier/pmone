<?php

namespace App\Support;

use App\Models\FormField;
use Illuminate\Support\Str;

class FormFieldTypes
{
    public const OPTION_TYPES = [
        FormField::TYPE_SELECT,
        FormField::TYPE_MULTI_SELECT,
        FormField::TYPE_CHECKBOX_GROUP,
        FormField::TYPE_RADIO,
    ];

    public const MULTI_VALUE_TYPES = [
        FormField::TYPE_MULTI_SELECT,
        FormField::TYPE_CHECKBOX_GROUP,
        FormField::TYPE_TAGS,
    ];

    /**
     * Analytics aggregation kind per type: options | numeric | text | none.
     *
     * @var array<string, string>
     */
    private const DEFINITIONS = [
        FormField::TYPE_TEXT => 'text',
        FormField::TYPE_TEXTAREA => 'text',
        FormField::TYPE_EMAIL => 'text',
        FormField::TYPE_NUMBER => 'numeric',
        FormField::TYPE_PHONE => 'text',
        FormField::TYPE_URL => 'text',
        FormField::TYPE_RICH_TEXT => 'text',
        FormField::TYPE_DATE => 'text',
        FormField::TYPE_TIME => 'text',
        FormField::TYPE_DATETIME => 'text',
        FormField::TYPE_DATE_RANGE => 'text',
        FormField::TYPE_SELECT => 'options',
        FormField::TYPE_MULTI_SELECT => 'options',
        FormField::TYPE_CHECKBOX => 'options',
        FormField::TYPE_CHECKBOX_GROUP => 'options',
        FormField::TYPE_RADIO => 'options',
        FormField::TYPE_TAGS => 'options',
        FormField::TYPE_SWITCH => 'options',
        FormField::TYPE_COUNTRY => 'options',
        FormField::TYPE_COLOR => 'options',
        FormField::TYPE_FILE => 'text',
        FormField::TYPE_RATING => 'numeric',
        FormField::TYPE_LINEAR_SCALE => 'numeric',
        FormField::TYPE_SLIDER => 'numeric',
        FormField::TYPE_SECTION => 'none',
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
        return $type !== FormField::TYPE_SECTION;
    }

    public static function analyticsKind(string $type): string
    {
        return self::DEFINITIONS[$type] ?? 'text';
    }

    /**
     * Build validation rules for a single field, keyed by the full input path.
     *
     * @return array<string, array<int, string>>
     */
    public static function rulesFor(FormField $field, string $key): array
    {
        $validation = $field->validation ?? [];
        $settings = $field->settings ?? [];
        $required = ! empty($validation['required']);

        $base = [$required ? 'required' : 'nullable'];
        $rules = [];

        switch ($field->type) {
            case FormField::TYPE_TEXT:
            case FormField::TYPE_TEXTAREA:
            case FormField::TYPE_RICH_TEXT:
                $base[] = 'string';
                if (isset($validation['min'])) {
                    $base[] = 'min:'.$validation['min'];
                }
                $base[] = 'max:'.($validation['max'] ?? 65535);
                break;

            case FormField::TYPE_EMAIL:
                $base[] = 'string';
                $base[] = 'email';
                $base[] = 'max:255';
                break;

            case FormField::TYPE_PHONE:
                $base[] = 'string';
                $base[] = 'max:30';
                break;

            case FormField::TYPE_URL:
                $base[] = 'string';
                $base[] = 'url';
                $base[] = 'max:2048';
                break;

            case FormField::TYPE_NUMBER:
            case FormField::TYPE_SLIDER:
                $base[] = 'numeric';
                if (isset($validation['min'])) {
                    $base[] = 'min:'.$validation['min'];
                }
                if (isset($validation['max'])) {
                    $base[] = 'max:'.$validation['max'];
                }
                break;

            case FormField::TYPE_DATE:
                $base[] = 'date_format:Y-m-d';
                break;

            case FormField::TYPE_TIME:
                $base[] = 'date_format:H:i';
                break;

            case FormField::TYPE_DATETIME:
                $base[] = 'date_format:Y-m-d H:i';
                break;

            case FormField::TYPE_DATE_RANGE:
                $base[] = 'array';
                $rules[$key.'.start'] = ['required_with:'.$key, 'date_format:Y-m-d'];
                $rules[$key.'.end'] = ['required_with:'.$key, 'date_format:Y-m-d', 'after_or_equal:'.$key.'.start'];
                break;

            case FormField::TYPE_SELECT:
            case FormField::TYPE_RADIO:
                $base[] = 'string';
                if ($optionValues = self::optionValues($field)) {
                    $base[] = 'in:'.implode(',', $optionValues);
                }
                break;

            case FormField::TYPE_MULTI_SELECT:
            case FormField::TYPE_CHECKBOX_GROUP:
                $base[] = 'array';
                if (isset($validation['min_selections'])) {
                    $base[] = 'min:'.$validation['min_selections'];
                }
                if (isset($validation['max_selections'])) {
                    $base[] = 'max:'.$validation['max_selections'];
                }
                $itemRules = ['string'];
                if ($optionValues = self::optionValues($field)) {
                    $itemRules[] = 'in:'.implode(',', $optionValues);
                }
                $rules[$key.'.*'] = $itemRules;
                break;

            case FormField::TYPE_TAGS:
                $base[] = 'array';
                if (isset($validation['max_selections'])) {
                    $base[] = 'max:'.$validation['max_selections'];
                }
                $rules[$key.'.*'] = ['string', 'max:100'];
                break;

            case FormField::TYPE_CHECKBOX:
            case FormField::TYPE_SWITCH:
                $base = $required ? ['accepted'] : ['nullable', 'boolean'];
                break;

            case FormField::TYPE_FILE:
                if (! empty($settings['multiple'])) {
                    $base[] = 'array';
                    $base[] = 'max:'.($validation['max_files'] ?? 5);
                    $rules[$key.'.*'] = ['string', 'starts_with:form-'];
                } else {
                    $base[] = 'string';
                    $base[] = 'starts_with:form-';
                }
                break;

            case FormField::TYPE_RATING:
                $base[] = 'integer';
                $base[] = 'min:1';
                $base[] = 'max:'.($settings['max'] ?? 5);
                break;

            case FormField::TYPE_LINEAR_SCALE:
                $base[] = 'integer';
                $base[] = 'min:'.($validation['min'] ?? 1);
                $base[] = 'max:'.($validation['max'] ?? 5);
                break;

            case FormField::TYPE_COLOR:
                $base[] = 'string';
                $base[] = 'regex:/^#[0-9a-fA-F]{6}$/';
                break;

            case FormField::TYPE_COUNTRY:
                $base[] = 'string';
                $base[] = 'max:100';
                break;

            case FormField::TYPE_SECTION:
                return [];
        }

        return [$key => $base] + $rules;
    }

    /**
     * Format a stored response value for export, email, and display.
     */
    public static function formatValue(FormField $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '-';
        }

        switch ($field->type) {
            case FormField::TYPE_SELECT:
            case FormField::TYPE_RADIO:
                return self::optionLabel($field, $value);

            case FormField::TYPE_MULTI_SELECT:
            case FormField::TYPE_CHECKBOX_GROUP:
                $values = is_array($value) ? $value : [$value];

                return implode(', ', array_map(fn ($v) => self::optionLabel($field, $v), $values));

            case FormField::TYPE_TAGS:
                return implode(', ', (array) $value);

            case FormField::TYPE_CHECKBOX:
            case FormField::TYPE_SWITCH:
                return $value ? 'Yes' : 'No';

            case FormField::TYPE_DATE_RANGE:
                if (is_array($value)) {
                    return trim(($value['start'] ?? '').' - '.($value['end'] ?? ''), ' -') ?: '-';
                }

                return (string) $value;

            case FormField::TYPE_RICH_TEXT:
                return Str::squish(strip_tags((string) $value)) ?: '-';

            case FormField::TYPE_FILE:
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
     * @return array<int, string>
     */
    public static function optionValues(FormField $field): array
    {
        return collect($field->options ?? [])
            ->pluck('value')
            ->filter(fn ($v) => is_scalar($v))
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    private static function optionLabel(FormField $field, mixed $value): string
    {
        $option = collect($field->options ?? [])->firstWhere('value', $value);

        return (string) ($option['label'] ?? (is_scalar($value) ? $value : json_encode($value)));
    }
}
