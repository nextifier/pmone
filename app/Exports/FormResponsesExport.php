<?php

namespace App\Exports;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Support\FormFieldTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FormResponsesExport extends BaseExport
{
    public function __construct(
        protected Form $form,
        ?array $filters = null,
        ?string $sort = null
    ) {
        parent::__construct($filters, $sort);
        $this->form->load('fields');
    }

    protected function getQuery(): Builder
    {
        return FormResponse::query()->where('form_id', $this->form->id);
    }

    protected function exportableFields(): Collection
    {
        return $this->form->fields
            ->reject(fn (FormField $field) => $field->type === FormField::TYPE_SECTION)
            ->sortBy('order_column')
            ->values();
    }

    public function headings(): array
    {
        $headings = $this->exportableFields()->pluck('label')->all();

        $headings[] = 'Email';
        $headings[] = 'IP Address';
        $headings[] = 'Submitted At';

        return $headings;
    }

    /**
     * @param  FormResponse  $response
     */
    public function map($response): array
    {
        $row = [];
        $responseData = $response->response_data ?? [];

        foreach ($this->exportableFields() as $field) {
            $row[] = FormFieldTypes::formatValue($field, $responseData[$field->ulid] ?? null);
        }

        $row[] = $response->respondent_email ?? '-';
        $row[] = $response->ip_address ?? '-';
        $row[] = $response->submitted_at?->format('Y-m-d H:i:s');

        return $row;
    }

    protected function applyFilters(Builder $query): void
    {
        if (! empty($this->filters['ids'])) {
            $query->whereIn('id', $this->filters['ids']);
        }

        if (isset($this->filters['search'])) {
            $query->search($this->filters['search']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort ?? '-submitted_at');

        if (in_array($field, ['submitted_at', 'created_at', 'respondent_email'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('submitted_at', 'desc');
        }
    }
}
