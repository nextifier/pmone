<?php

namespace App\Exports;

use App\Models\Attendee;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
use App\Models\User;
use App\Support\FormFieldTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AttendeesExport extends BaseExport
{
    private const MONEY_COLUMN = 'N';

    /** @var Collection<int, CustomField>|null */
    private ?Collection $customFieldsCache = null;

    /** @var array<int, array<int, mixed>> Buyer answers keyed by [user_id][field_id]. */
    private array $answersByUser = [];

    protected function getQuery(): Builder
    {
        return Attendee::query()
            ->with([
                'ticket',
                'ticketOrderItem.selectedEventDay',
                'ticketOrderItem.ticketSession',
                'ticketOrderItem.ticketOrder.paymentGateway',
            ])
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return array_merge([
            'Name',
            'Email',
            'Phone',
            'Ticket',
            'Tier',
            'Day',
            'Session',
            'Checked In',
            'Checked In At',
            'Order Number',
            'Order Status',
            'Payment Channel',
            'Mode',
            'Total',
            'Created At',
        ], $this->customFields()->map(fn (CustomField $f) => $this->fieldLabel($f))->all());
    }

    public function map($model): array
    {
        $item = $model->ticketOrderItem;
        $order = $item?->ticketOrder;
        $day = $item?->selectedEventDay;
        $session = $item?->ticketSession;

        $row = [
            $model->name ?: '-',
            $model->email ?: '-',
            $this->cleanPhone($model->phone),
            $model->ticket?->getTranslation('title', app()->getLocale(), false) ?: '-',
            $model->ticket?->tier ?: '-',
            $day ? ($this->localized($day->label) ?: 'Day '.$day->day_number) : '-',
            $session ? ($this->localized($session->label) ?: '-') : '-',
            $model->checked_in_at ? 'Yes' : 'No',
            $model->checked_in_at?->format('Y-m-d H:i') ?? '-',
            $order?->order_number ?: '-',
            $order?->status?->label() ?? '-',
            $order?->payment_channel ?: '-',
            $order?->paymentGateway?->mode ?: '-',
            $order ? (float) $order->total : 0,
            $model->created_at?->format('Y-m-d H:i') ?? '-',
        ];

        // Business-matching intake answers belong to the order's buyer, so every
        // attendee of an order shows the same answers.
        $answers = $this->answersByUser[$order?->user_id] ?? [];
        foreach ($this->customFields() as $field) {
            $row[] = FormFieldTypes::formatStoredValue($field->type, $answers[$field->id] ?? null, $field->options ?? []);
        }

        return $row;
    }

    /**
     * Active business-matching custom fields for the exported event (columns),
     * loaded once together with every buyer's answers.
     *
     * @return Collection<int, CustomField>
     */
    private function customFields(): Collection
    {
        if ($this->customFieldsCache !== null) {
            return $this->customFieldsCache;
        }

        $eventId = $this->filters['event_id'] ?? null;
        if (! $eventId) {
            return $this->customFieldsCache = collect();
        }

        $this->customFieldsCache = CustomField::query()
            ->where('fieldable_type', Event::class)
            ->where('fieldable_id', $eventId)
            ->where('context', CustomField::CONTEXT_BUSINESS_MATCHING)
            ->where('is_active', true)
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

    protected function phoneColumns(): array
    {
        return ['C'];
    }

    public function columnFormats(): array
    {
        $formats = parent::columnFormats();
        $formats[self::MONEY_COLUMN] = NumberFormat::FORMAT_NUMBER;

        return $formats;
    }

    protected function applyFilters(Builder $query): void
    {
        if ($eventId = $this->filters['event_id'] ?? null) {
            $query->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->where('event_id', $eventId));
        }

        if ($search = $this->filters['search'] ?? null) {
            $like = '%'.strtolower((string) $search).'%';
            $query->where(function ($q) use ($like, $search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                    ->orWhere('qr_token', $search)
                    ->orWhereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->whereRaw('LOWER(order_number) LIKE ?', [$like]));
            });
        }

        if (($checkedIn = $this->filters['checked_in'] ?? null) !== null) {
            $values = is_array($checkedIn) ? $checkedIn : explode(',', (string) $checkedIn);
            $wantsIn = in_array('in', $values, true);
            $wantsOut = in_array('out', $values, true);
            if ($wantsIn && ! $wantsOut) {
                $query->whereNotNull('checked_in_at');
            } elseif ($wantsOut && ! $wantsIn) {
                $query->whereNull('checked_in_at');
            }
        }

        if ($channels = $this->arrayFilter($this->filters['payment_channel'] ?? null)) {
            $query->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->whereIn('payment_channel', $channels));
        }

        if ($modes = $this->arrayFilter($this->filters['mode'] ?? null)) {
            $query->whereHas('ticketOrderItem.ticketOrder.paymentGateway', fn ($g) => $g->whereIn('mode', $modes));
        }

        if ($statuses = $this->arrayFilter($this->filters['order_status'] ?? null)) {
            $query->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->whereIn('status', $statuses));
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        $allowed = ['id', 'name', 'checked_in_at', 'created_at'];

        if (in_array($field, $allowed, true)) {
            $query->reorder($field, $direction);
        }
    }

    private function cleanPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '-';
        }

        return preg_replace('/[^\d]/', '', $phone) ?: '-';
    }

    private function localized(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? $value['en'] ?? reset($value) ?? '');
        }

        return $value !== null ? (string) $value : '';
    }

    /**
     * @return array<int, string>
     */
    private function arrayFilter(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_filter(is_array($value) ? $value : explode(',', (string) $value)));
    }
}
