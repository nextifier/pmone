<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SheetsController extends Controller
{
    public function orders(Request $request, int $eventId): JsonResponse
    {
        if ($request->query('token') !== config('services.sheets.api_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = Event::find($eventId);

        if (! $event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $orders = Order::query()
            ->whereIn('brand_event_id', BrandEvent::where('event_id', $eventId)->select('id'))
            ->with(['brandEvent.brand', 'brandEvent.sales', 'items.productCategory', 'creator'])
            ->orderByDesc('submitted_at')
            ->get();

        $headings = [
            'ID', 'Order Number', 'Brand Name', 'Company Name',
            'Booth Type', 'Booth Number', 'Booth Size (sqm)', 'Booth Price',
            'Fascia Name', 'Badge Name', 'Sales PIC', 'Order Period',
            'Product Name', 'Product Category', 'Qty', 'Unit Price', 'Item Total', 'Item Notes',
            'Subtotal', 'Discount Amount', 'Penalty Amount', 'Promo Code',
            'Tax Rate (%)', 'Tax Amount', 'Total',
            'Operational Status', 'Payment Status', 'Cancellation Reason', 'Order Notes',
            'Submitted At', 'Confirmed At', 'Created By',
        ];

        $rows = [];

        foreach ($orders as $order) {
            $brand = $order->brandEvent?->brand;
            $brandEvent = $order->brandEvent;
            $items = $order->items;

            $orderFields = [
                $order->id,
                $order->order_number,
                $brand?->name ?? '-',
                $brand?->company_name ?? '-',
                $brandEvent?->booth_type?->label() ?? '-',
                $brandEvent?->booth_number ?? '-',
                $brandEvent?->booth_size,
                $brandEvent?->booth_price,
                $brandEvent?->fascia_name ?? '-',
                $brandEvent?->badge_name ?? '-',
                $brandEvent?->sales?->name ?? '-',
                $order->order_period ? ucwords(str_replace('_', ' ', $order->order_period)) : '-',
            ];

            $orderSummary = [
                $order->subtotal,
                $order->discount_amount,
                $order->penalty_amount,
                $order->promo_code_applied ?? '-',
                $order->tax_rate,
                $order->tax_amount,
                $order->total,
                $order->operational_status?->label() ?? '-',
                $order->payment_status?->label() ?? '-',
                $order->cancellation_reason,
                $order->notes,
                $order->submitted_at?->format('Y-m-d H:i:s'),
                $order->confirmed_at?->format('Y-m-d H:i:s'),
                $order->creator?->name ?? '-',
            ];

            if ($items->isEmpty()) {
                $rows[] = array_merge($orderFields, ['-', '-', 0, 0, 0, '-'], $orderSummary);
            } else {
                foreach ($items as $item) {
                    $rows[] = array_merge(
                        $orderFields,
                        [
                            $item->product_name,
                            $item->productCategory?->title ?? '-',
                            $item->quantity,
                            $item->unit_price,
                            $item->total_price,
                            $item->notes,
                        ],
                        $orderSummary,
                    );
                }
            }
        }

        return response()->json([
            'event' => $event->title,
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

        $headings = [
            'ID', 'ULID', 'Name', 'Slug',
            'Company Name', 'Company Email', 'Company Phone', 'Company Address',
            'Description', 'Status',
            'Logo URL',
            'Business Categories', 'Other Tags',
            'Events Count', 'Events List', 'Booth Numbers', 'Sales PICs',
            'Users List',
            'Links Count',
            ...$linkLabels,
            ...$linkClickHeadings,
            'Total Visits', 'Total Link Clicks',
            'Total Promotion Posts',
            'Created By', 'Updated By', 'Created At', 'Updated At',
            'Custom Fields',
        ];

        $rows = $brands->map(function (Brand $brand) use ($linkLabels) {
            $logoUrl = $brand->getFirstMediaUrl('brand_logo', 'md') ?: '-';

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

            $customFields = $brand->custom_fields
                ? json_encode($brand->custom_fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '-';

            return [
                $brand->id,
                $brand->ulid,
                $brand->name,
                $brand->slug,
                $brand->company_name ?? '-',
                $brand->company_email ?? '-',
                $brand->company_phone ?? '-',
                $brand->company_address ?? '-',
                $brand->description ?? '-',
                Str::title(str_replace('_', ' ', $brand->status ?? '-')),
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
                $customFields,
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

        // Dynamic operational-document columns (non-event-rule docs only).
        // This sheet spans all events, so columns are qualified by event title
        // and submissions are keyed by event_id + booth_identifier.
        $eventIds = $brandEvents->pluck('event_id')->filter()->unique()->values()->all();
        $eventTitles = $brandEvents->pluck('event')->filter()->unique('id')
            ->mapWithKeys(fn ($e) => [$e->id => $e->title])->all();

        $operationalDocs = EventDocument::query()
            ->whereIn('event_id', $eventIds)
            ->ordered()
            ->get()
            ->reject(fn (EventDocument $doc) => $doc->isEventRule())
            ->values();

        $documentSubmissions = EventDocumentSubmission::query()
            ->whereIn('event_id', $eventIds)
            ->with('media')
            ->get()
            ->groupBy(fn (EventDocumentSubmission $s) => $s->event_id.'|'.$s->booth_identifier);

        $headings = [
            'ID',
            'Brand ID', 'Brand ULID', 'Brand Name', 'Brand Slug',
            'Company Name', 'Company Email', 'Company Phone', 'Company Address',
            'Brand Description', 'Brand Status', 'Brand Logo URL',
            'Business Categories', 'Other Tags',
            'Brand Users',
            'Event ID', 'Event Title', 'Event Slug',
            'Event Start Date', 'Event End Date', 'Event Location', 'Event Hall', 'Event Status',
            'Booth Number', 'Booth Size (sqm)', 'Booth Type', 'Booth Price',
            'Fascia Name', 'Badge Name',
            'Sales PIC Name', 'Sales PIC Email', 'Sales PIC Phone',
            'Participation Status', 'Notes', 'Promotion Post Limit',
            'Visits Count', 'Clicks Count', 'Promotion Posts Count',
            'Brand Links Count',
            ...$linkLabels,
            ...$linkClickHeadings,
            'Brand Total Link Clicks',
            'Brand Created By', 'Brand Updated By', 'Brand Custom Fields',
            'BrandEvent Custom Fields',
            'Created At', 'Updated At',
        ];

        foreach ($operationalDocs as $doc) {
            $eventTitle = $eventTitles[$doc->event_id] ?? ('Event '.$doc->event_id);
            $headings[] = 'Doc: ['.$eventTitle.'] '.$doc->title;
        }

        $rows = $brandEvents->map(function (BrandEvent $brandEvent) use ($linkLabels, $operationalDocs, $documentSubmissions) {
            $brand = $brandEvent->brand;
            $event = $brandEvent->event;
            $sales = $brandEvent->sales;

            $logoUrl = $brand?->getFirstMediaUrl('brand_logo', 'md') ?: '-';

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

            $brandCustomFields = $brand && $brand->custom_fields
                ? json_encode($brand->custom_fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '-';

            $brandEventCustomFields = $brandEvent->custom_fields
                ? json_encode($brandEvent->custom_fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '-';

            $row = [
                $brandEvent->id,
                $brand?->id ?? '-',
                $brand?->ulid ?? '-',
                $brand?->name ?? '-',
                $brand?->slug ?? '-',
                $brand?->company_name ?? '-',
                $brand?->company_email ?? '-',
                $brand?->company_phone ?? '-',
                $brand?->company_address ?? '-',
                $brand?->description ?? '-',
                $brand ? Str::title(str_replace('_', ' ', $brand->status ?? '-')) : '-',
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
                $brandCustomFields,
                $brandEventCustomFields,
                $brandEvent->created_at?->format('Y-m-d H:i:s'),
                $brandEvent->updated_at?->format('Y-m-d H:i:s'),
            ];

            // Append operational-document submission values for this brand-event.
            $boothIdentifier = $brandEvent->booth_number ?: 'be-'.$brandEvent->id;
            $rowSubmissions = $documentSubmissions->get($brandEvent->event_id.'|'.$boothIdentifier, collect());

            foreach ($operationalDocs as $doc) {
                if ($doc->event_id !== $brandEvent->event_id) {
                    $row[] = '';

                    continue;
                }

                $submission = $rowSubmissions->firstWhere('event_document_id', $doc->id);

                if (! $submission) {
                    $row[] = '-';
                } elseif ($doc->document_type === 'checkbox_agreement') {
                    $row[] = $submission->agreed_at ? 'Agreed ('.$submission->submitted_at?->format('Y-m-d H:i').')' : '-';
                } elseif ($doc->document_type === 'file_upload') {
                    $row[] = strtok($submission->getFirstMediaUrl('submission_file'), '?') ?: '-';
                } elseif ($doc->document_type === 'text_input') {
                    $row[] = $submission->text_value ?: '-';
                } else {
                    $row[] = '-';
                }
            }

            return $row;
        })->toArray();

        return response()->json([
            'title' => 'Brand Events',
            'headings' => $headings,
            'rows' => $rows,
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
