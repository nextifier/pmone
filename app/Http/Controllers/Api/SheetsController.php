<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\Order;
use App\Models\Project;
use App\Support\Sheets\SheetFormatting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SheetsController extends Controller
{
    public function orders(Request $request): JsonResponse
    {
        if ($request->query('token') !== config('services.sheets.api_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $brandEventTable = (new BrandEvent)->getTable();

        $orders = Order::query()
            ->with(['brandEvent.brand', 'brandEvent.event:id,title', 'brandEvent.sales', 'items.productCategory', 'adjustments', 'creator'])
            ->orderBy(BrandEvent::select('event_id')->whereColumn("{$brandEventTable}.id", 'orders.brand_event_id'))
            ->orderByDesc('submitted_at')
            ->get();

        $headings = [
            'ID', 'Order Number', 'Event ID', 'Event Title',
            'Brand Name', 'Company Name', 'Country',
            'Booth Type', 'Booth Number', 'Booth Size (sqm)', 'Booth Price',
            'Fascia Name', 'Badge Name', 'Sales PIC', 'Order Period', 'Source',
            'Product Name', 'Product Category', 'Qty', 'Unit Price', 'Item Total',
            'Item Notes', 'Item Internal Notes',
            'Subtotal', 'Discount Amount', 'Penalty Amount', 'Promo Code', 'Adjustment Reason',
            'Tax Rate (%)', 'Tax Amount', 'Total',
            'Operational Status', 'Payment Status', 'Cancellation Reason',
            'Order Notes', 'Order Internal Notes',
            'Submitted At', 'Confirmed At', 'Created By',
            'Currency', 'Exchange Rate (to IDR)', 'Total (IDR)',
        ];

        $rows = [];

        foreach ($orders as $order) {
            $brand = $order->brandEvent?->brand;
            $brandEvent = $order->brandEvent;
            $event = $brandEvent?->event;
            $items = $order->items;

            $orderIdentity = [
                $order->id,
                $order->order_number,
                $event?->id ?? '-',
                $event?->title ?? '-',
                $brand?->name ?? '-',
                $brand?->company_name ?? '-',
                data_get($brand?->address, 'country') ?? '-',
                $brandEvent?->booth_type?->label() ?? '-',
                $brandEvent?->booth_number ?? '-',
                $brandEvent?->booth_size,
                $brandEvent?->booth_price,
                $brandEvent?->fascia_name ?? '-',
                $brandEvent?->badge_name ?? '-',
                $brandEvent?->sales?->name ?? '-',
                $order->order_period ? ucwords(str_replace('_', ' ', $order->order_period)) : '-',
                Str::title($order->source ?? '-'),
            ];

            $orderSummary = [
                $order->subtotal,
                $order->discount_amount,
                $order->penalty_amount,
                $order->promo_code_applied ?? '-',
                $this->adjustmentReason($order),
                $order->tax_rate,
                $order->tax_amount,
                $order->total,
                $order->operational_status?->label() ?? '-',
                $order->payment_status?->label() ?? '-',
                $order->cancellation_reason,
                $order->notes,
                $order->internal_notes,
                $order->submitted_at?->format('Y-m-d H:i:s'),
                $order->confirmed_at?->format('Y-m-d H:i:s'),
                $order->creator?->name ?? '-',
                $order->currency ?? 'IDR',
                (float) $order->exchange_rate_to_idr,
                (float) $order->total_idr,
            ];

            if ($items->isEmpty()) {
                $rows[] = array_merge($orderIdentity, ['-', '-', 0, 0, 0, '-', '-'], $orderSummary);
            } else {
                foreach ($items as $item) {
                    $rows[] = array_merge(
                        $orderIdentity,
                        [
                            $item->product_name,
                            $item->productCategory?->title ?? '-',
                            $item->quantity,
                            $item->unit_price,
                            $item->total_price,
                            $item->notes,
                            $item->internal_notes,
                        ],
                        $orderSummary,
                    );
                }
            }
        }

        return response()->json([
            'title' => 'Orders',
            'headings' => $headings,
            'rows' => $rows,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        if ($request->query('token') !== config('services.sheets.api_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $contacts = Contact::query()
            ->with(['tags', 'projects'])
            ->orderBy('name')
            ->get();

        $headings = [
            'ID', 'ULID', 'Name', 'Job Title', 'Emails', 'Phones',
            'Company Name', 'Website', 'Country', 'Province', 'City', 'Street Address',
            'Status', 'Source', 'Contact Types', 'Business Categories',
            'Tags', 'Projects', 'Notes', 'Created At', 'Updated At',
        ];

        $rows = $contacts->map(function (Contact $contact) {
            $emails = is_array($contact->emails) ? implode(', ', $contact->emails) : '-';
            $phones = is_array($contact->phones) ? implode(', ', $contact->phones) : '-';

            $types = $contact->tags
                ->filter(fn ($tag) => $tag->type === 'contact_type')
                ->pluck('name')
                ->join(', ') ?: '-';

            $categories = $contact->tags
                ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
                ->pluck('name')
                ->unique()
                ->join(', ') ?: '-';

            $tags = $contact->tags
                ->filter(fn ($tag) => $tag->type === 'contact_tag')
                ->pluck('name')
                ->join(', ') ?: '-';

            $projects = $contact->projects->pluck('name')->join(', ') ?: '-';
            $address = $contact->address;

            return [
                $contact->id,
                $contact->ulid,
                $contact->name,
                $contact->job_title ?? '-',
                $emails,
                $phones,
                $contact->company_name ?? '-',
                $contact->website ?? '-',
                $address['country'] ?? '-',
                $address['province'] ?? '-',
                $address['city'] ?? '-',
                $address['street'] ?? '-',
                Str::title(str_replace('_', ' ', $contact->status->value)),
                Str::title(str_replace('_', ' ', $contact->source ?? '-')),
                $types,
                $categories,
                $tags,
                $projects,
                $contact->notes ?? '-',
                $contact->created_at?->format('Y-m-d H:i:s'),
                $contact->updated_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return response()->json([
            'title' => 'Contacts',
            'headings' => $headings,
            'rows' => $rows,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function brands(Request $request): JsonResponse
    {
        if ($request->query('token') !== config('services.sheets.api_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $brands = Brand::query()
            ->with([
                'media',
                'creator:id,name',
                'updater:id,name',
                'tags',
                'events:id,title',
                'brandEvents' => fn ($q) => $q
                    ->with(['event:id,title', 'sales:id,name'])
                    ->withCount(['visits', 'promotionPosts']),
                'users:id,name',
                'links' => fn ($q) => $q->withCount('clicks'),
            ])
            ->withCount(['brandEvents', 'users', 'links'])
            ->orderBy('created_at')
            ->get();

        $linkLabels = $brands
            ->flatMap(fn (Brand $b) => $b->links->pluck('label'))
            ->filter()
            ->map(fn ($l) => trim($l))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->values()
            ->all();

        $linkClickHeadings = array_map(fn ($l) => "{$l} Click", $linkLabels);

        $brandFieldDefs = $this->brandCustomFieldDefinitions();

        $headings = [
            'ID', 'ULID', 'Name', 'Slug',
            'Company Name', 'Company Email', 'Company Phone', 'Country', 'Province', 'City', 'Street Address',
            'Description', 'Status',
            'Profile Image URL', 'Logo URL',
            'Business Categories', 'Other Tags',
            'Events Count', 'Events List', 'Booth Numbers', 'Sales PICs',
            'Users List',
            'Links Count',
            ...$linkLabels,
            ...$linkClickHeadings,
            'Total Visits', 'Total Link Clicks',
            'Total Promotion Posts',
            'Created By', 'Updated By', 'Created At', 'Updated At',
            ...$brandFieldDefs->map(fn (array $col) => $col['label'])->all(),
        ];

        $rows = $brands->map(function (Brand $brand) use ($linkLabels, $brandFieldDefs) {
            $profileImageUrl = $brand->getFirstMediaUrl('profile_image', 'md') ?: '-';
            $logoUrl = $brand->getFirstMediaUrl('brand_logo') ?: '-';

            $categories = $brand->tags
                ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
                ->pluck('name')
                ->unique()
                ->join(', ') ?: '-';

            $otherTags = $brand->tags
                ->reject(fn ($tag) => str_starts_with($tag->type, 'business_category'))
                ->pluck('name')
                ->join(', ') ?: '-';

            $eventsList = $brand->events->pluck('title')->join(', ') ?: '-';

            $boothNumbers = $brand->brandEvents
                ->pluck('booth_number')
                ->filter()
                ->join(', ') ?: '-';

            $salesPics = $brand->brandEvents
                ->pluck('sales.name')
                ->filter()
                ->unique()
                ->join(', ') ?: '-';

            $usersList = $brand->users
                ->map(fn ($user) => $user->pivot?->role
                    ? "{$user->name} ({$user->pivot->role})"
                    : $user->name)
                ->join(', ') ?: '-';

            $linksByLabel = $brand->links->keyBy(fn ($link) => trim($link->label ?? ''));

            $linkUrlSlots = [];
            $linkClickSlots = [];
            foreach ($linkLabels as $label) {
                $link = $linksByLabel[$label] ?? null;
                $linkUrlSlots[] = $link?->url ?? '';
                $linkClickSlots[] = $link ? (int) $link->clicks_count : '';
            }

            $totalVisits = (int) $brand->brandEvents->sum('visits_count');
            $totalLinkClicks = (int) $brand->links->sum('clicks_count');

            $totalPromotionPosts = (int) $brand->brandEvents->sum('promotion_posts_count');

            return [
                $brand->id,
                $brand->ulid,
                $brand->name,
                $brand->slug,
                $brand->company_name ?? '-',
                $brand->company_email ?? '-',
                $brand->company_phone ?? '-',
                data_get($brand->address, 'country') ?? '-',
                data_get($brand->address, 'province') ?? '-',
                data_get($brand->address, 'city') ?? '-',
                data_get($brand->address, 'street') ?? '-',
                $brand->description ?? '-',
                Str::title(str_replace('_', ' ', $brand->status ?? '-')),
                $profileImageUrl,
                $logoUrl,
                $categories,
                $otherTags,
                $brand->brand_events_count,
                $eventsList,
                $boothNumbers,
                $salesPics,
                $usersList,
                $brand->links_count,
                ...$linkUrlSlots,
                ...$linkClickSlots,
                $totalVisits,
                $totalLinkClicks,
                $totalPromotionPosts,
                $brand->creator?->name ?? '-',
                $brand->updater?->name ?? '-',
                $brand->created_at?->format('Y-m-d H:i:s'),
                $brand->updated_at?->format('Y-m-d H:i:s'),
                ...SheetFormatting::customFieldColumns($brandFieldDefs, $brand->custom_fields),
            ];
        })->toArray();

        return response()->json([
            'title' => 'Brands',
            'headings' => $headings,
            'rows' => $rows,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function brandEvents(Request $request): JsonResponse
    {
        if ($request->query('token') !== config('services.sheets.api_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $brandEvents = BrandEvent::query()
            ->with([
                'brand' => fn ($q) => $q
                    ->with([
                        'media',
                        'creator:id,name',
                        'updater:id,name',
                        'tags',
                        'users:id,name',
                        'links' => fn ($q) => $q->withCount('clicks'),
                    ])
                    ->withCount('links'),
                'event:id,title,slug,start_date,end_date,location,hall,status',
                'sales:id,name,email,phone',
            ])
            ->withCount(['promotionPosts', 'visits', 'clicks'])
            ->orderBy('created_at')
            ->get();

        $linkLabels = $brandEvents
            ->pluck('brand')
            ->filter()
            ->unique('id')
            ->flatMap(fn (Brand $b) => $b->links->pluck('label'))
            ->filter()
            ->map(fn ($l) => trim($l))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->values()
            ->all();

        $linkClickHeadings = array_map(fn ($l) => "{$l} Click", $linkLabels);

        // Dynamic custom-field columns, appended last. Brand fields are typed
        // (formatted via the field catalog); BrandEvent fields have no catalog
        // so they are rendered from their raw jsonb keys.
        $brandFieldDefs = $this->brandCustomFieldDefinitions();
        $brandFieldLabels = $brandFieldDefs->map(fn (array $col) => $col['label'])->all();

        $brandEventFieldKeys = $brandEvents
            ->flatMap(fn (BrandEvent $be) => array_keys($be->custom_fields ?? []))
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Suffix BrandEvent field headers that would collide with a brand field.
        $brandEventFieldHeadings = array_map(function (string $key) use ($brandFieldLabels) {
            $header = SheetFormatting::headline($key);

            return in_array($header, $brandFieldLabels, true) ? "{$header} (Booth)" : $header;
        }, $brandEventFieldKeys);

        $headings = [
            'ID',
            'Brand ID', 'Brand ULID', 'Brand Name', 'Brand Slug',
            'Company Name', 'Company Email', 'Company Phone', 'Country', 'Province', 'City', 'Street Address',
            'Brand Description', 'Brand Status', 'Profile Image URL', 'Brand Logo URL',
            'Business Categories', 'Other Tags',
            'Brand Users',
            'Event ID', 'Event Title', 'Event Slug',
            'Event Start Date', 'Event End Date', 'Event Location', 'Event Hall', 'Event Status',
            'Booth Number', 'Booth Size (sqm)', 'Booth Type', 'Booth Price', 'Order Currency',
            'Fascia Name', 'Badge Name',
            'Sales PIC Name', 'Sales PIC Email', 'Sales PIC Phone',
            'Participation Status', 'Notes', 'Promotion Post Limit',
            'Visits Count', 'Clicks Count', 'Promotion Posts Count',
            'Brand Links Count',
            ...$linkLabels,
            ...$linkClickHeadings,
            'Brand Total Link Clicks',
            'Brand Created By', 'Brand Updated By',
            'Created At', 'Updated At',
            ...$brandFieldLabels,
            ...$brandEventFieldHeadings,
        ];

        $rows = $brandEvents->map(function (BrandEvent $brandEvent) use ($linkLabels, $brandFieldDefs, $brandEventFieldKeys) {
            $brand = $brandEvent->brand;
            $event = $brandEvent->event;
            $sales = $brandEvent->sales;

            $profileImageUrl = $brand?->getFirstMediaUrl('profile_image', 'md') ?: '-';
            $logoUrl = $brand?->getFirstMediaUrl('brand_logo') ?: '-';

            $categories = $brand
                ? ($brand->tags
                    ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
                    ->pluck('name')
                    ->unique()
                    ->join(', ') ?: '-')
                : '-';

            $otherTags = $brand
                ? ($brand->tags
                    ->reject(fn ($tag) => str_starts_with($tag->type, 'business_category'))
                    ->pluck('name')
                    ->join(', ') ?: '-')
                : '-';

            $usersList = $brand
                ? ($brand->users
                    ->map(fn ($user) => $user->pivot?->role
                        ? "{$user->name} ({$user->pivot->role})"
                        : $user->name)
                    ->join(', ') ?: '-')
                : '-';

            $linksByLabel = $brand
                ? $brand->links->keyBy(fn ($link) => trim($link->label ?? ''))
                : collect();

            $linkUrlSlots = [];
            $linkClickSlots = [];
            foreach ($linkLabels as $label) {
                $link = $linksByLabel[$label] ?? null;
                $linkUrlSlots[] = $link?->url ?? '';
                $linkClickSlots[] = $link ? (int) $link->clicks_count : '';
            }

            $totalLinkClicks = $brand ? (int) $brand->links->sum('clicks_count') : 0;

            return [
                $brandEvent->id,
                $brand?->id ?? '-',
                $brand?->ulid ?? '-',
                $brand?->name ?? '-',
                $brand?->slug ?? '-',
                $brand?->company_name ?? '-',
                $brand?->company_email ?? '-',
                $brand?->company_phone ?? '-',
                data_get($brand?->address, 'country') ?? '-',
                data_get($brand?->address, 'province') ?? '-',
                data_get($brand?->address, 'city') ?? '-',
                data_get($brand?->address, 'street') ?? '-',
                $brand?->description ?? '-',
                $brand ? Str::title(str_replace('_', ' ', $brand->status ?? '-')) : '-',
                $profileImageUrl,
                $logoUrl,
                $categories,
                $otherTags,
                $usersList,
                $event?->id ?? '-',
                $event?->title ?? '-',
                $event?->slug ?? '-',
                $event?->start_date?->format('Y-m-d H:i:s') ?? '-',
                $event?->end_date?->format('Y-m-d H:i:s') ?? '-',
                $event?->location ?? '-',
                $event?->hall ?? '-',
                $event ? Str::title(str_replace('_', ' ', $event->status ?? '-')) : '-',
                $brandEvent->booth_number ?? '-',
                $brandEvent->booth_size,
                $brandEvent->booth_type?->label() ?? '-',
                $brandEvent->booth_price,
                $brandEvent->resolveCurrency(),
                $brandEvent->fascia_name ?? '-',
                $brandEvent->badge_name ?? '-',
                $sales?->name ?? '-',
                $sales?->email ?? '-',
                $sales?->phone ?? '-',
                Str::title(str_replace('_', ' ', $brandEvent->status ?? '-')),
                $brandEvent->notes ?? '-',
                $brandEvent->promotion_post_limit,
                (int) $brandEvent->visits_count,
                (int) $brandEvent->clicks_count,
                (int) $brandEvent->promotion_posts_count,
                (int) ($brand?->links_count ?? 0),
                ...$linkUrlSlots,
                ...$linkClickSlots,
                $totalLinkClicks,
                $brand?->creator?->name ?? '-',
                $brand?->updater?->name ?? '-',
                $brandEvent->created_at?->format('Y-m-d H:i:s'),
                $brandEvent->updated_at?->format('Y-m-d H:i:s'),
                ...SheetFormatting::customFieldColumns($brandFieldDefs, $brand?->custom_fields),
                ...SheetFormatting::freeJsonColumns($brandEventFieldKeys, $brandEvent->custom_fields),
            ];
        })->toArray();

        return response()->json([
            'title' => 'Brand Events',
            'headings' => $headings,
            'rows' => $rows,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Brand-context custom-field columns across every project, mapped to one
     * column per field label (case-insensitive, English label). Fields sharing a
     * label across projects collapse into a single column whose value is read
     * from whichever of the underlying keys holds it. These drive the dynamic
     * Brand / BrandEvent custom-field columns.
     *
     * @return Collection<int, array{label: string, type: string, options: array, keys: array<int, string>}>
     */
    private function brandCustomFieldDefinitions(): Collection
    {
        return CustomField::query()
            ->context(CustomField::CONTEXT_BRAND)
            ->where('fieldable_type', Project::class)
            ->ordered()
            ->get()
            ->groupBy(fn (CustomField $field) => mb_strtolower(trim((string) $field->getTranslation('label', 'en'))))
            ->map(function (Collection $group) {
                // Group order follows order_column; pick the lowest-id member as
                // the canonical definition so a same-label duplicate never wins
                // the label/type/options non-deterministically.
                $canonical = $group->sortBy('id')->first();

                return [
                    'label' => (string) $canonical->label,
                    'type' => $canonical->type,
                    'options' => $canonical->options ?? [],
                    'keys' => $group->pluck('key')->unique()->values()->all(),
                ];
            })
            ->values();
    }

    /**
     * Operational Documents sheet: one row per (brand event x applicable
     * document), covering both event rules and operational docs. The file
     * history column is the audit trail (from media version metadata), not the
     * 30-day activity log.
     */
    public function operationalDocuments(Request $request): JsonResponse
    {
        if ($request->query('token') !== config('services.sheets.api_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $brandEvents = BrandEvent::query()
            ->with(['brand:id,name,company_name', 'event:id,title'])
            ->orderBy('event_id')
            ->orderBy('created_at')
            ->get();

        $eventIds = $brandEvents->pluck('event_id')->filter()->unique()->values()->all();

        $documentsByEvent = EventDocument::query()
            ->whereIn('event_id', $eventIds)
            ->with('fields')
            ->ordered()
            ->get()
            ->groupBy('event_id');

        $submissions = EventDocumentSubmission::query()
            ->whereIn('event_id', $eventIds)
            ->with(['media', 'submitter:id,name'])
            ->get()
            ->groupBy(fn (EventDocumentSubmission $s) => $s->event_id.'|'.$s->booth_identifier);

        // Union of mini-form field labels across every document that appears,
        // so answer columns are stable across the sheet.
        $fieldLabels = $documentsByEvent
            ->flatten(1)
            ->flatMap(fn (EventDocument $doc) => $doc->fields->where('is_active', true))
            ->unique('ulid')
            ->map(fn (CustomField $field) => (string) $field->label)
            ->unique()
            ->values()
            ->all();

        $headings = [
            'Brand Event ID', 'Brand ID', 'Brand Name', 'Company Name',
            'Event ID', 'Event Title', 'Booth Number', 'Booth Type',
            'Document ID', 'Document Title', 'Document Kind', 'Required', 'Blocks Next Step', 'Submission Deadline',
            'Status',
            'Agreed At', 'Agreed Version', 'Current Content Version',
            'Submitted By', 'Submitted At', 'IP Address',
            'Current Files', 'File Versions Count', 'Last Upload At', 'Last Upload By', 'File History',
            ...$fieldLabels,
        ];

        $rows = [];

        foreach ($brandEvents as $brandEvent) {
            $brand = $brandEvent->brand;
            $documents = $documentsByEvent->get($brandEvent->event_id, collect())
                ->filter(fn (EventDocument $doc) => $doc->appliesToBoothType($brandEvent->booth_type?->value));

            $boothIdentifier = $brandEvent->booth_number ?: 'be-'.$brandEvent->id;
            $rowSubmissions = $submissions->get($brandEvent->event_id.'|'.$boothIdentifier, collect());

            foreach ($documents as $doc) {
                $submission = $rowSubmissions->firstWhere('event_document_id', $doc->id);

                $status = 'Not Submitted';
                if ($submission) {
                    if ($submission->document_version < $doc->content_version) {
                        $status = 'Needs Re-agreement';
                    } elseif ($doc->isSubmissionComplete($submission)) {
                        $status = 'Completed';
                    }
                }

                $currentFiles = $submission
                    ? $submission->currentSubmissionFiles()
                        ->map(fn ($media) => strtok($media->getUrl(), '?'))
                        ->implode(', ')
                    : '';

                $lastUpload = $submission?->getMedia('submission_file')
                    ->sortByDesc(fn ($media) => $media->created_at)
                    ->first();

                $rows[] = [
                    $brandEvent->id,
                    $brand?->id ?? '-',
                    $brand?->name ?? '-',
                    $brand?->company_name ?? '-',
                    $brandEvent->event_id,
                    $brandEvent->event?->title ?? '-',
                    $brandEvent->booth_number ?? '-',
                    $brandEvent->booth_type?->label() ?? '-',
                    $doc->id,
                    $doc->title,
                    $doc->isEventRule() ? 'Event Rule' : 'Operational',
                    $doc->is_required ? 'Yes' : 'No',
                    $doc->blocks_next_step ? 'Yes' : 'No',
                    SheetFormatting::dateTime($doc->submission_deadline),
                    $status,
                    $submission ? SheetFormatting::dateTime($submission->agreed_at) : '-',
                    $submission?->document_version ?? '-',
                    $doc->content_version,
                    $submission?->submitter?->name ?? '-',
                    $submission ? SheetFormatting::dateTime($submission->submitted_at) : '-',
                    $submission?->ip_address ?? '-',
                    $currentFiles ?: '-',
                    $submission ? $submission->getMedia('submission_file')->count() : 0,
                    $lastUpload ? SheetFormatting::dateTime($lastUpload->created_at) : '-',
                    $lastUpload?->getCustomProperty('uploaded_by_name') ?? '-',
                    $submission ? $this->fileHistorySummary($submission) : '-',
                    ...$this->documentFieldAnswers($doc, $submission, $fieldLabels),
                ];
            }
        }

        return response()->json([
            'title' => 'Operational Documents',
            'headings' => $headings,
            'rows' => $rows,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Compact per-field file version history for a submission.
     */
    private function fileHistorySummary(EventDocumentSubmission $submission): string
    {
        $media = $submission->getMedia('submission_file');

        if ($media->isEmpty()) {
            return '-';
        }

        return $media
            ->groupBy(fn ($m) => $m->getCustomProperty('field_ulid') ?? 'legacy')
            ->map(function ($group) {
                return $group
                    ->sortByDesc(fn ($m) => (int) $m->getCustomProperty('version', 1))
                    ->map(function ($m) {
                        $version = (int) $m->getCustomProperty('version', 1);
                        $by = $m->getCustomProperty('uploaded_by_name') ?? 'unknown';
                        $at = SheetFormatting::dateTime($m->created_at);
                        $state = $m->getCustomProperty('superseded_at') === null ? 'current' : 'superseded';

                        return "v{$version} {$m->file_name} - {$by}, {$at} ({$state})";
                    })
                    ->implode('; ');
            })
            ->implode(' | ');
    }

    /**
     * Answer cell per mini-form field label (aligned with the sheet's union of
     * labels), formatted by the field's type.
     *
     * @param  array<int, string>  $fieldLabels
     * @return array<int, string>
     */
    private function documentFieldAnswers(EventDocument $document, ?EventDocumentSubmission $submission, array $fieldLabels): array
    {
        $byLabel = $document->fields
            ->where('is_active', true)
            ->keyBy(fn (CustomField $field) => (string) $field->label);

        return array_map(function (string $label) use ($byLabel, $document, $submission) {
            $field = $byLabel->get($label);

            if (! $field || ! $submission) {
                return '';
            }

            return $document->submissionFieldValue($field, $submission);
        }, $fieldLabels);
    }

    /**
     * Human-readable adjustment reasons for an order, joined by '; '. Voided
     * adjustments are annotated with their void reason.
     */
    private function adjustmentReason(Order $order): string
    {
        if (! $order->relationLoaded('adjustments') || $order->adjustments->isEmpty()) {
            return '-';
        }

        return $order->adjustments
            ->map(function ($adjustment) {
                $reason = $adjustment->rule_snapshot['reason'] ?? $adjustment->label;

                if ($adjustment->voided_at !== null) {
                    return "{$reason} (voided: ".($adjustment->void_reason ?: 'no reason').')';
                }

                return $reason;
            })
            ->filter()
            ->implode('; ') ?: '-';
    }
}
