<?php

namespace App\Exports;

use App\Models\BrandEvent;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BrandEventsExport extends BaseExport
{
    /** @var Collection<int, EventDocument> */
    protected Collection $eventDocuments;

    /** @var Collection<string, Collection<int, EventDocumentSubmission>> Keyed by booth_identifier */
    protected Collection $documentSubmissions;

    public function __construct(
        protected int $eventId,
        protected ?array $filters = null,
        protected ?string $sort = null
    ) {
        $this->eventDocuments = EventDocument::where('event_id', $this->eventId)
            ->ordered()
            ->get();

        $this->documentSubmissions = EventDocumentSubmission::where('event_id', $this->eventId)
            ->get()
            ->groupBy('booth_identifier');
    }

    protected function getQuery(): Builder
    {
        return BrandEvent::query()
            ->where('event_id', $this->eventId)
            ->with(['brand.media', 'brand.tags', 'brand.users', 'sales'])
            ->withCount('promotionPosts');
    }

    protected function phoneColumns(): array
    {
        return ['F'];
    }

    public function headings(): array
    {
        $headings = [
            'ID',
            'Brand Name',
            'Company Name',
            'Company Email',
            'Company Address',
            'Company Phone',
            'Status',
            'Logo URL',
            'Booth Number',
            'Booth Size (sqm)',
            'Booth Type',
            'Booth Price',
            'Fascia Name',
            'Badge Name',
            'Categories',
            'Sales',
            'PIC Members',
            'Notes',
            'Promo Posts',
            'Promo Post Limit',
            'Created At',
            'Updated At',
        ];

        // Add document columns dynamically
        foreach ($this->eventDocuments as $doc) {
            $headings[] = 'Doc: '.$doc->title;
        }

        return $headings;
    }

    /**
     * @param  BrandEvent  $brandEvent
     */
    public function map($brandEvent): array
    {
        $brand = $brandEvent->brand;

        // Get brand logo original URL (strip version query string)
        $logoUrl = $this->cleanMediaUrl($brand->getFirstMediaUrl('brand_logo'));

        // Get PIC members (emails only)
        $members = $brand->relationLoaded('users')
            ? $brand->users->pluck('email')->implode(', ')
            : '-';

        // Categories
        $categories = $brand->relationLoaded('tags')
            ? ($brand->business_categories_list ? implode(', ', $brand->business_categories_list) : '-')
            : '-';

        // Booth identifier for document submissions
        $boothIdentifier = $brandEvent->booth_number ?: 'be-'.$brandEvent->id;
        $submissions = $this->documentSubmissions->get($boothIdentifier, collect());

        $row = [
            $brandEvent->id,
            $brand->name ?? '-',
            $brand->company_name ?? '-',
            $brand->company_email ?? '-',
            $brand->company_address ?? '-',
            $brand->company_phone ?? '-',
            $this->titleCase($brandEvent->status),
            $logoUrl,
            $brandEvent->booth_number ?? '-',
            $brandEvent->booth_size ?? '-',
            $brandEvent->booth_type?->label() ?? '-',
            $brandEvent->booth_price ?? '-',
            $brandEvent->fascia_name ?? '-',
            $brandEvent->badge_name ?? '-',
            $categories,
            $brandEvent->sales?->name ?? '-',
            $members ?: '-',
            $brandEvent->notes ?? '-',
            (int) ($brandEvent->promotion_posts_count ?? 0),
            $brandEvent->promotion_post_limit,
            $brandEvent->created_at?->format('Y-m-d H:i:s'),
            $brandEvent->updated_at?->format('Y-m-d H:i:s'),
        ];

        // Add document submission status for each document
        foreach ($this->eventDocuments as $doc) {
            $submission = $submissions->firstWhere('event_document_id', $doc->id);

            if (! $submission) {
                $row[] = '-';
            } elseif ($doc->document_type === 'checkbox_agreement') {
                $row[] = $submission->agreed_at ? 'Agreed ('.$submission->submitted_at?->format('Y-m-d H:i').')' : '-';
            } elseif ($doc->document_type === 'file_upload') {
                $row[] = $this->cleanMediaUrl($submission->getFirstMediaUrl('submission_file'));
            } elseif ($doc->document_type === 'text_input') {
                $row[] = $submission->text_value ?: '-';
            } else {
                $row[] = '-';
            }
        }

        return $row;
    }

    protected function cleanMediaUrl(string $url): string
    {
        if (! $url) {
            return '-';
        }

        return strtok($url, '?') ?: '-';
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->whereHas('brand', function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort ?? 'order_column');

        if (in_array($field, ['order_column', 'status', 'booth_number', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('order_column', 'asc');
        }
    }
}
