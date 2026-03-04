<?php

namespace App\Exports;

use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Database\Eloquent\Builder;

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

    public function headings(): array
    {
        $headings = [];

        foreach ($this->form->fields->sortBy('order_column') as $field) {
            $headings[] = $field->label;
        }

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

        foreach ($this->form->fields->sortBy('order_column') as $field) {
            $value = $responseData[$field->ulid] ?? '-';

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $row[] = $value ?: '-';
        }

        $row[] = $response->respondent_email ?? '-';
        $row[] = $response->ip_address ?? '-';
        $row[] = $response->submitted_at?->format('Y-m-d H:i:s');

        return $row;
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(respondent_email) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(response_data::text) LIKE ?', ["%{$searchTerm}%"]);
            });
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
