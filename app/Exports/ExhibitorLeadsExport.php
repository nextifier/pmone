<?php

namespace App\Exports;

use App\Models\Brand;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
use App\Models\ExhibitorLead;
use App\Models\User;
use App\Support\FormFieldTypes;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exhibitor's own leads as an Excel sheet. Scoped to a single brand so an
 * exhibitor never exports another exhibitor's data. Business-matching intake
 * answers (given by the lead's buyer at checkout) are appended as columns.
 */
class ExhibitorLeadsExport implements FromCollection, WithHeadings, WithMapping
{
    /** @var Collection<int, CustomField>|null */
    private ?Collection $customFieldsCache = null;

    /** @var array<int, array<int, mixed>> Buyer answers keyed by [user_id][field_id]. */
    private array $answersByUser = [];

    public function __construct(protected Brand $brand) {}

    public function collection()
    {
        return ExhibitorLead::query()
            ->where('brand_id', $this->brand->id)
            ->with(['attendee.ticketOrderItem.ticketOrder', 'event'])
            ->orderByDesc('scanned_at')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_merge(
            ['Name', 'Email', 'Phone', 'Ticket Tier', 'Event', 'Scanned At'],
            $this->customFields()->map(fn (CustomField $f) => $this->fieldLabel($f))->all(),
        );
    }

    /**
     * @param  ExhibitorLead  $lead
     * @return array<int, mixed>
     */
    public function map($lead): array
    {
        $snapshot = $lead->snapshot ?? [];

        $row = [
            $snapshot['name'] ?? $lead->attendee?->name,
            $snapshot['email'] ?? $lead->attendee?->email,
            $snapshot['phone'] ?? $lead->attendee?->phone,
            $snapshot['ticket_tier'] ?? null,
            $lead->event?->title,
            $lead->scanned_at?->format('Y-m-d H:i'),
        ];

        // Answers belong to the lead's buyer and only to this lead's event fields.
        $buyerId = $lead->attendee?->ticketOrderItem?->ticketOrder?->user_id;
        $answers = $this->answersByUser[$buyerId] ?? [];
        foreach ($this->customFields() as $field) {
            $value = $field->event_id === $lead->event_id ? ($answers[$field->id] ?? null) : null;
            $row[] = FormFieldTypes::formatStoredValue($field->type, $value, $field->options ?? []);
        }

        return $row;
    }

    /**
     * Active business-matching custom fields across every event this brand has
     * leads in (columns), loaded once with every buyer's answers.
     *
     * @return Collection<int, CustomField>
     */
    private function customFields(): Collection
    {
        if ($this->customFieldsCache !== null) {
            return $this->customFieldsCache;
        }

        $eventIds = ExhibitorLead::query()
            ->where('brand_id', $this->brand->id)
            ->distinct()
            ->pluck('event_id');

        if ($eventIds->isEmpty()) {
            return $this->customFieldsCache = collect();
        }

        $this->customFieldsCache = CustomField::query()
            ->where('fieldable_type', Event::class)
            ->whereIn('fieldable_id', $eventIds)
            ->where('context', CustomField::CONTEXT_BUSINESS_MATCHING)
            ->where('is_active', true)
            ->orderBy('fieldable_id')
            ->orderBy('order_column')
            ->get();

        if ($this->customFieldsCache->isNotEmpty()) {
            CustomFieldValue::query()
                ->whereIn('custom_field_id', $this->customFieldsCache->pluck('id'))
                ->where('subject_type', User::class)
                ->get(['subject_id', 'custom_field_id', 'value'])
                ->each(function (CustomFieldValue $r): void {
                    $this->answersByUser[$r->subject_id][$r->custom_field_id] = $r->value;
                });
        }

        return $this->customFieldsCache;
    }

    private function fieldLabel(CustomField $field): string
    {
        return $field->getTranslation('label', app()->getLocale(), false)
            ?: $field->getTranslation('label', 'en', false)
            ?: '-';
    }
}
