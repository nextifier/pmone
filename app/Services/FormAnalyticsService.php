<?php

namespace App\Services;

use App\Helpers\DateRangeHelper;
use App\Models\CustomField;
use App\Models\Form;
use App\Support\FormFieldTypes;
use Illuminate\Support\Carbon;

class FormAnalyticsService
{
    private const TEXT_SAMPLE_LIMIT = 5;

    private const DISTRIBUTION_LIMIT = 25;

    /**
     * @return array{summary: array, responses_per_day: array, fields: array}
     */
    public function analyze(Form $form, string $period = '30'): array
    {
        $form->loadMissing('fields');

        return [
            'summary' => $this->buildSummary($form),
            'responses_per_day' => $this->buildTimeSeries($form, $period),
            'fields' => $this->aggregateFields($form),
        ];
    }

    private function buildSummary(Form $form): array
    {
        $statusBreakdown = $form->responses()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total_responses' => $form->responses()->count(),
            'today' => $form->responses()->where('submitted_at', '>=', now()->startOfDay())->count(),
            'last_7_days' => $form->responses()->where('submitted_at', '>=', now()->subDays(7)->startOfDay())->count(),
            'last_30_days' => $form->responses()->where('submitted_at', '>=', now()->subDays(30)->startOfDay())->count(),
            'response_limit' => $form->response_limit,
            'status_breakdown' => [
                'new' => (int) ($statusBreakdown['new'] ?? 0),
                'read' => (int) ($statusBreakdown['read'] ?? 0),
                'starred' => (int) ($statusBreakdown['starred'] ?? 0),
                'spam' => (int) ($statusBreakdown['spam'] ?? 0),
            ],
            'first_response_at' => $form->responses()->min('submitted_at'),
            'last_response_at' => $form->responses()->max('submitted_at'),
        ];
    }

    private function buildTimeSeries(Form $form, string $period): array
    {
        if ($period === 'all') {
            $firstResponseAt = $form->responses()->min('submitted_at');
            $startDate = $firstResponseAt
                ? Carbon::parse($firstResponseAt)->startOfDay()
                : now()->subDays(30)->startOfDay();
            $endDate = now()->endOfDay();
        } else {
            $dateRange = DateRangeHelper::getDateRange($period);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];
        }

        $counts = $form->responses()
            ->where('submitted_at', '>=', $startDate)
            ->where('submitted_at', '<=', $endDate)
            ->selectRaw('DATE(submitted_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $series = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->toDateString();
            $series[] = [
                'date' => $dateString,
                'count' => $counts->has($dateString) ? (int) $counts[$dateString]->count : 0,
            ];
            $currentDate->addDay();
        }

        return $series;
    }

    private function aggregateFields(Form $form): array
    {
        $fields = $form->fields->reject(
            fn (CustomField $field) => $field->type === CustomField::TYPE_SECTION
        )->values();

        if ($fields->isEmpty()) {
            return [];
        }

        $accumulators = [];
        foreach ($fields as $field) {
            $accumulators[$field->ulid] = [
                'answered' => 0,
                'counts' => [],
                'sum' => 0.0,
                'min' => null,
                'max' => null,
                'samples' => [],
            ];
        }

        $totalResponses = 0;

        $form->responses()
            ->select(['id', 'response_data'])
            ->chunkById(500, function ($responses) use ($fields, &$accumulators, &$totalResponses) {
                foreach ($responses as $response) {
                    $totalResponses++;
                    $data = $response->response_data ?? [];

                    foreach ($fields as $field) {
                        $value = $data[$field->ulid] ?? null;

                        if ($value === null || $value === '' || $value === []) {
                            continue;
                        }

                        $this->accumulate($field, $value, $accumulators[$field->ulid]);
                    }
                }
            });

        return $fields->map(
            fn (CustomField $field) => $this->buildFieldResult($field, $accumulators[$field->ulid], $totalResponses)
        )->all();
    }

    private function accumulate(CustomField $field, mixed $value, array &$acc): void
    {
        $acc['answered']++;

        switch (FormFieldTypes::analyticsKind($field->type)) {
            case 'options':
                $values = $this->normalizeOptionValues($field, $value);
                foreach ($values as $key) {
                    $acc['counts'][$key] = ($acc['counts'][$key] ?? 0) + 1;
                }
                break;

            case 'numeric':
                if (! is_numeric($value)) {
                    break;
                }
                $number = (float) $value;
                $acc['sum'] += $number;
                $acc['min'] = $acc['min'] === null ? $number : min($acc['min'], $number);
                $acc['max'] = $acc['max'] === null ? $number : max($acc['max'], $number);
                $key = (string) ($number == (int) $number ? (int) $number : $number);
                $acc['counts'][$key] = ($acc['counts'][$key] ?? 0) + 1;
                break;

            case 'text':
                if ($field->type === CustomField::TYPE_FILE) {
                    break;
                }
                $formatted = FormFieldTypes::formatValue($field, $value);
                if ($formatted !== '-') {
                    $acc['samples'][] = $formatted;
                    $acc['samples'] = array_slice($acc['samples'], -self::TEXT_SAMPLE_LIMIT);
                }
                break;
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeOptionValues(CustomField $field, mixed $value): array
    {
        if (in_array($field->type, [CustomField::TYPE_CHECKBOX, CustomField::TYPE_SWITCH], true)) {
            return [$value ? 'Yes' : 'No'];
        }

        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(
            fn ($v) => is_scalar($v) ? (string) $v : null,
            $values
        ), fn ($v) => $v !== null && $v !== ''));
    }

    private function buildFieldResult(CustomField $field, array $acc, int $totalResponses): array
    {
        $kind = FormFieldTypes::analyticsKind($field->type);
        $answered = $acc['answered'];

        $result = [
            'ulid' => $field->ulid,
            'label' => $field->label,
            'type' => $field->type,
            'aggregation' => $kind,
            'answered_count' => $answered,
            'skipped_count' => max(0, $totalResponses - $answered),
        ];

        if ($kind === 'options') {
            $result['options'] = $this->buildOptionRows($field, $acc['counts'], $answered);
        } elseif ($kind === 'numeric') {
            $result['average'] = $answered > 0 ? round($acc['sum'] / $answered, 2) : null;
            $result['min'] = $acc['min'];
            $result['max'] = $acc['max'];
            $result['distribution'] = $this->buildDistribution($field, $acc['counts']);
        } else {
            $result['latest'] = $field->type === CustomField::TYPE_FILE ? [] : array_reverse($acc['samples']);
        }

        return $result;
    }

    private function buildOptionRows(CustomField $field, array $counts, int $answered): array
    {
        $rows = [];

        if (in_array($field->type, [CustomField::TYPE_CHECKBOX, CustomField::TYPE_SWITCH], true)) {
            $definedOptions = collect([
                ['value' => 'Yes', 'label' => 'Yes'],
                ['value' => 'No', 'label' => 'No'],
            ]);
        } else {
            $definedOptions = collect($field->options ?? [])
                ->filter(fn ($option) => isset($option['value']))
                ->map(fn ($option) => [
                    'value' => (string) $option['value'],
                    'label' => (string) ($option['label'] ?? $option['value']),
                ]);
        }

        foreach ($definedOptions as $option) {
            $count = $counts[$option['value']] ?? 0;
            unset($counts[$option['value']]);
            $rows[] = $this->optionRow($option['value'], $option['label'], $count, $answered);
        }

        arsort($counts);
        foreach (array_slice($counts, 0, self::DISTRIBUTION_LIMIT, true) as $value => $count) {
            $rows[] = $this->optionRow((string) $value, (string) $value, $count, $answered);
        }

        if ($definedOptions->isEmpty()) {
            usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        return $rows;
    }

    private function optionRow(string $value, string $label, int $count, int $answered): array
    {
        return [
            'value' => $value,
            'label' => $label,
            'count' => $count,
            'percentage' => $answered > 0 ? round($count / $answered * 100, 1) : 0.0,
        ];
    }

    private function buildDistribution(CustomField $field, array $counts): array
    {
        if (in_array($field->type, [CustomField::TYPE_RATING, CustomField::TYPE_LINEAR_SCALE], true)) {
            $min = $field->type === CustomField::TYPE_RATING ? 1 : (int) ($field->validation['min'] ?? 1);
            $max = $field->type === CustomField::TYPE_RATING
                ? (int) ($field->settings['max'] ?? 5)
                : (int) ($field->validation['max'] ?? 5);

            $distribution = [];
            for ($value = $min; $value <= $max; $value++) {
                $distribution[] = [
                    'value' => $value,
                    'count' => (int) ($counts[(string) $value] ?? 0),
                ];
            }

            return $distribution;
        }

        if (count($counts) > self::DISTRIBUTION_LIMIT) {
            return [];
        }

        ksort($counts, SORT_NUMERIC);

        return collect($counts)
            ->map(fn ($count, $value) => ['value' => $value + 0, 'count' => (int) $count])
            ->values()
            ->all();
    }
}
