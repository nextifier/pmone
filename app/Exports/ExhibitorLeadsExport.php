<?php

namespace App\Exports;

use App\Models\Brand;
use App\Models\ExhibitorLead;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exhibitor's own leads as an Excel sheet. Scoped to a single brand so an
 * exhibitor never exports another exhibitor's data.
 */
class ExhibitorLeadsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected Brand $brand) {}

    public function collection()
    {
        return ExhibitorLead::query()
            ->where('brand_id', $this->brand->id)
            ->with(['attendee', 'event'])
            ->orderByDesc('scanned_at')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Ticket Tier', 'Event', 'Scanned At'];
    }

    /**
     * @param  ExhibitorLead  $lead
     * @return array<int, mixed>
     */
    public function map($lead): array
    {
        $snapshot = $lead->snapshot ?? [];

        return [
            $snapshot['name'] ?? $lead->attendee?->name,
            $snapshot['email'] ?? $lead->attendee?->email,
            $snapshot['phone'] ?? $lead->attendee?->phone,
            $snapshot['ticket_tier'] ?? null,
            $lead->event?->title,
            $lead->scanned_at?->format('Y-m-d H:i'),
        ];
    }
}
