<?php

namespace App\Exports;

use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EmailMessagesExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return EmailMessage::query();
    }

    public function headings(): array
    {
        return [
            'To',
            'Status',
            'Bounce Type',
            'Subject',
            'From',
            'Message ID',
            'Sent At',
        ];
    }

    /**
     * @param  EmailMessage  $message
     */
    public function map($message): array
    {
        return [
            implode(', ', $message->recipients ?? []),
            $message->status?->label() ?? '-',
            $message->bounce_type ? Str::title($message->bounce_type) : '-',
            $message->subject ?? '-',
            $message->from_address ?? '-',
            $message->message_id,
            $message->sent_at?->format('Y-m-d H:i:s') ?? '-',
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (! empty($this->filters['status'])) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $this->filters['status']))));

            if ($statuses) {
                $query->whereIn('status', $statuses);
            }
        }

        if (! empty($this->filters['date_from'])) {
            $query->where('sent_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
        }

        if (! empty($this->filters['date_to'])) {
            $query->where('sent_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
        }

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            // Mirror the list endpoint: recipients is a json array, so match it
            // by casting to text rather than whereJsonContains (which is exact).
            $recipientsLike = '%'.mb_strtolower($search).'%';

            $query->where(function (Builder $query) use ($search, $recipientsLike) {
                $query->whereLike('subject', "%{$search}%")
                    ->orWhereLike('from_address', "%{$search}%")
                    ->orWhereLike('message_id', "%{$search}%")
                    ->orWhereRaw('LOWER(CAST(recipients AS TEXT)) LIKE ?', [$recipientsLike]);
            });
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['sent_at', 'status', 'subject'], true)) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('sent_at', 'desc');
        }
    }
}
