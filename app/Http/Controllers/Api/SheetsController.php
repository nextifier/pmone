<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdjustmentKind;
use App\Http\Controllers\Controller;
use App\Models\AppliedAdjustment;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\Order;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Support\Sheets\SheetFormatting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\MediaStream;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Feeds for the Google Sheets Apps Script integration. Every endpoint returns
 * the same `title` / `headings` / `rows` / `updated_at` shape, so one script
 * serves them all.
 *
 * Each sheet comes in two flavours: a global feed covering every event, and an
 * `/events/{event}/...` feed scoped to a single event. The scoped feeds exist
 * because the global ones grow in both rows and columns as events are added -
 * dynamic columns are derived from the data (one per brand link label, one per
 * brand custom field across every project).
 */
class SheetsController extends Controller
{
    public function orders(): JsonResponse
    {
        return response()->json($this->ordersPayload());
    }

    public function eventOrders(Event $event): JsonResponse
    {
        return response()->json($this->ordersPayload($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function ordersPayload(?Event $event = null): array
    {
        $brandEventTable = (new BrandEvent)->getTable();

        $orders = Order::query()
            ->with(['brandEvent.brand', 'brandEvent.event:id,title', 'brandEvent.sales', 'items.productCategory', 'adjustments', 'creator'])
            ->when(
                $event,
                fn ($query) => $query->whereRelation('brandEvent', "{$brandEventTable}.event_id", $event->id),
                // Unscoped: group the rows by event through a correlated subquery.
                // Pointless once every row belongs to the same event.
                fn ($query) => $query->orderBy(
                    BrandEvent::select('event_id')->whereColumn("{$brandEventTable}.id", 'orders.brand_event_id')
                ),
            )
            ->orderByDesc('submitted_at')
            ->get();

        // One row per order ITEM. The order-level cells repeat on every row of a
        // multi-item order so filtering by brand/event still works, which is why
        // they are all prefixed "Order " and why `Is First Line` exists: sum them
        // with SUMIF(Is First Line = TRUE; ...) or every order is counted once
        // per item. The per-item adjustment columns are the ones that carry a
        // discount attached to a single product - `Order Discount Amount` is the
        // total of item-scoped AND order-scoped discounts together, so on its own
        // it cannot say which line was discounted.
        $headings = [
            'ID', 'Order Number', 'Event ID', 'Event Title',
            'Brand Name', 'Company Name', 'Country',
            'Booth Type', 'Booth Number', 'Booth Size (sqm)', 'Booth Price',
            'Fascia Name', 'Badge Name', 'Sales PIC', 'Order Period', 'Source',
            'Product Name', 'Product Category', 'Qty', 'Unit Price', 'Item Total',
            'Item Discount', 'Item Penalty', 'Item Net Total', 'Item Adjustment Note',
            'Item Notes', 'Item Internal Notes',
            'Line #', 'Is First Line',
            'Order Subtotal', 'Order Discount Amount', 'Order Penalty Amount', 'Promo Code', 'Order Adjustment Reason',
            'Tax Rate (%)', 'Order Tax Amount', 'Order Total',
            'Operational Status', 'Payment Status', 'Cancellation Reason',
            'Order Notes', 'Order Internal Notes',
            'Submitted At', 'Confirmed At', 'Created By',
            'Currency', 'Exchange Rate (to IDR)', 'Order Total (IDR)',
        ];

        $rows = [];

        foreach ($orders as $order) {
            $brand = $order->brandEvent?->brand;
            $brandEvent = $order->brandEvent;
            $orderEvent = $brandEvent?->event;
            $items = $order->items;

            // Live per-item adjustments, grouped in memory - `adjustments` is
            // already eager-loaded, so this costs no extra query. Voided rows are
            // dropped: their `amount` is never zeroed (it stays as the audit
            // record of what once applied) and would otherwise read as live.
            $itemAdjustments = $order->adjustments
                ->filter(fn (AppliedAdjustment $a) => $a->order_item_id !== null && $a->voided_at === null)
                ->groupBy('order_item_id');

            $orderIdentity = [
                $order->id,
                $order->order_number,
                $orderEvent?->id ?? '-',
                $orderEvent?->title ?? '-',
                $brand?->name ?? '-',
                $brand?->company_name ?? '-',
                data_get($brand?->address, 'country') ?? '-',
                $brandEvent?->booth_type?->label() ?? '-',
                $brandEvent?->booth_number ?? '-',
                self::money($brandEvent?->booth_size),
                self::money($brandEvent?->booth_price),
                $brandEvent?->fascia_name ?? '-',
                $brandEvent?->badge_name ?? '-',
                $brandEvent?->sales?->name ?? '-',
                $order->order_period ? ucwords(str_replace('_', ' ', $order->order_period)) : '-',
                Str::title($order->source ?? '-'),
            ];

            $orderSummary = [
                self::money($order->subtotal),
                self::money($order->discount_amount),
                self::money($order->penalty_amount),
                $order->promo_code_applied ?? '-',
                $this->adjustmentReason($order),
                self::money($order->tax_rate),
                self::money($order->tax_amount),
                self::money($order->total),
                $order->operational_status?->label() ?? '-',
                $order->payment_status?->label() ?? '-',
                $order->cancellation_reason,
                $order->notes,
                $order->internal_notes,
                $order->submitted_at?->format('Y-m-d H:i:s'),
                $order->confirmed_at?->format('Y-m-d H:i:s'),
                $order->creator?->name ?? '-',
                $order->currency ?? 'IDR',
                self::money($order->exchange_rate_to_idr),
                self::money($order->total_idr),
            ];

            if ($items->isEmpty()) {
                $rows[] = array_merge(
                    $orderIdentity,
                    ['-', '-', 0, 0.0, 0.0, 0.0, 0.0, 0.0, '-', '-', '-', 1, true],
                    $orderSummary,
                );

                continue;
            }

            $line = 0;

            foreach ($items as $item) {
                $line++;

                $adjustments = $itemAdjustments->get($item->id, collect());
                $itemDiscount = (float) $adjustments
                    ->filter(fn (AppliedAdjustment $a) => $a->kind === AdjustmentKind::Discount)
                    ->sum('amount');
                $itemPenalty = (float) $adjustments
                    ->filter(fn (AppliedAdjustment $a) => $a->kind === AdjustmentKind::Penalty)
                    ->sum('amount');

                $rows[] = array_merge(
                    $orderIdentity,
                    [
                        $item->product_name,
                        $item->productCategory?->title ?? '-',
                        $item->quantity,
                        self::money($item->unit_price),
                        self::money($item->total_price),
                        $itemDiscount,
                        $itemPenalty,
                        (float) $item->total_price - $itemDiscount + $itemPenalty,
                        $this->itemAdjustmentNote($adjustments),
                        $item->notes,
                        $item->internal_notes,
                        $line,
                        $line === 1,
                    ],
                    $orderSummary,
                );
            }
        }

        return $this->payload('Orders', $event, $headings, $rows);
    }

    public function contacts(): JsonResponse
    {
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

        return response()->json($this->payload('Contacts', null, $headings, $rows));
    }

    public function brands(): JsonResponse
    {
        return response()->json($this->brandsPayload());
    }

    public function eventBrands(Event $event): JsonResponse
    {
        return response()->json($this->brandsPayload($event));
    }

    /**
     * Scoped to an event, this lists only the brands taking part in it, and the
     * brand-event aggregates (booth numbers, sales PICs, visits, promotion
     * posts) count that event alone. Link and click columns stay brand-level
     * because links themselves are not tied to an event.
     *
     * @return array<string, mixed>
     */
    private function brandsPayload(?Event $event = null): array
    {
        $eventId = $event?->id;
        $brandEventTable = (new BrandEvent)->getTable();

        $brands = Brand::query()
            ->when($eventId, fn ($query) => $query->whereRelation('brandEvents', "{$brandEventTable}.event_id", $eventId))
            ->with([
                'media',
                'creator:id,name',
                'updater:id,name',
                'tags',
                // "Events List". A BelongsToMany needs table-qualified columns
                // here, which is what the 'events:id,title' shorthand does for
                // us when there is no constraint to add.
                'events' => fn ($q) => $q
                    ->select('events.id', 'events.title')
                    ->when($eventId, fn ($sub) => $sub->where('events.id', $eventId)),
                // Booth Numbers, Sales PICs, Total Visits and Total Promotion
                // Posts are all read off this collection, so constraining it
                // here scopes those four columns for free.
                'brandEvents' => fn ($q) => $q
                    ->when($eventId, fn ($sub) => $sub->where("{$brandEventTable}.event_id", $eventId))
                    ->with(['event:id,title', 'sales:id,name'])
                    ->withCount(['visits', 'promotionPosts']),
                'users:id,name',
                'links' => fn ($q) => $q->withCount('clicks'),
            ])
            ->withCount([
                'brandEvents' => fn ($q) => $q->when($eventId, fn ($sub) => $sub->where("{$brandEventTable}.event_id", $eventId)),
                'users',
                'links',
            ])
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

        $brandFieldDefs = $this->brandCustomFieldDefinitions($event);

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

        return $this->payload('Brands', $event, $headings, $rows);
    }

    public function brandEvents(): JsonResponse
    {
        return response()->json($this->brandEventsPayload());
    }

    public function eventBrandEvents(Event $event): JsonResponse
    {
        return response()->json($this->brandEventsPayload($event));
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * The brand-event's promotion post images as one downloadable thing, for the
     * "Promotion Post Image Link" cell in the Brand Events sheet. One image is
     * served as itself; several are zipped.
     *
     * MediaStream rather than ZipArchive: it streams through `Media::stream()`
     * (Flysystem), so it keeps working once MEDIA_DISK flips to R2 - the
     * ZipArchive path in PostExportService resolves real filesystem paths and
     * would break there.
     */
    public function promotionPostImages(BrandEvent $brandEvent): StreamedResponse|BinaryFileResponse
    {
        $images = $brandEvent->promotionPosts()
            ->with('media')
            ->get()
            ->flatMap(fn (PromotionPost $post) => $post->getMedia('post_image'));

        abort_if($images->isEmpty(), 404, 'This brand has no promotion post images.');

        $brandEvent->loadMissing(['brand:id,name,slug', 'event:id,slug']);
        $stem = Str::slug(trim(($brandEvent->brand?->slug ?? 'brand').'-'.($brandEvent->event?->slug ?? '')), '-');

        if ($images->count() === 1) {
            $media = $images->first();

            return Storage::disk($media->disk)->download(
                $media->getPathRelativeToRoot(),
                $this->downloadFileName($media),
            );
        }

        // ZipStream would otherwise emit its own headers mid-stream, after
        // Symfony has already sent the response's - leaving the declared type as
        // MediaStream's `application/octet-stream` on the way out and ZipStream's
        // non-standard `application/x-zip` on the wire. Silence it and declare
        // the canonical type once.
        $response = MediaStream::create("promotion-images-{$stem}.zip")
            ->useZipOptions(function (array &$options) {
                $options['sendHttpHeaders'] = false;
            })
            ->addMedia($images)
            ->toResponse(request());

        $response->headers->set('Content-Type', 'application/zip');

        return $response;
    }

    private function brandEventsPayload(?Event $event = null): array
    {
        $brandEvents = BrandEvent::query()
            ->when($event, fn ($query) => $query->where('event_id', $event->id))
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
            ->withCount([
                'promotionPosts',
                // Only "does it have any" is needed for the download link, so
                // count instead of eager-loading media across every brand-event.
                'promotionPosts as promotion_post_images_count' => fn ($q) => $q->whereHas(
                    'media',
                    fn ($m) => $m->where('collection_name', 'post_image'),
                ),
                'visits',
                'clicks',
            ])
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
        $brandFieldDefs = $this->brandCustomFieldDefinitions($event);
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
            'Visits Count', 'Clicks Count', 'Promotion Posts Count', 'Promotion Post Image Link',
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
                $this->promotionImagesLink($brandEvent),
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

        return $this->payload('Brand Events', $event, $headings, $rows);
    }

    /**
     * Brand-context custom-field columns, mapped to one column per field label
     * (case-insensitive, English label). Fields sharing a label across projects
     * collapse into a single column whose value is read from whichever of the
     * underlying keys holds it. These drive the dynamic Brand / BrandEvent
     * custom-field columns.
     *
     * Passing an event narrows the catalog to that event's project, which is
     * where most of the column savings on the scoped sheets come from. Without
     * one, every project's brand fields are included.
     *
     * @return Collection<int, array{label: string, type: string, options: array, keys: array<int, string>}>
     */
    private function brandCustomFieldDefinitions(?Event $event = null): Collection
    {
        return CustomField::query()
            ->context(CustomField::CONTEXT_BRAND)
            ->where('fieldable_type', Project::class)
            ->when($event, fn ($query) => $query->where('fieldable_id', $event->project_id))
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
                    'settings' => $canonical->settings ?? [],
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
    public function operationalDocuments(): JsonResponse
    {
        return response()->json($this->operationalDocumentsPayload());
    }

    public function eventOperationalDocuments(Event $event): JsonResponse
    {
        return response()->json($this->operationalDocumentsPayload($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalDocumentsPayload(?Event $event = null): array
    {
        $brandEvents = BrandEvent::query()
            ->when($event, fn ($query) => $query->where('event_id', $event->id))
            ->with(['brand:id,name,company_name', 'event:id,title'])
            ->orderBy('event_id')
            ->orderBy('created_at')
            ->get();

        // Scoped, take the event id from the scope rather than from the brand
        // events: an event with documents but no booths yet must still emit its
        // mini-form answer columns, only with no rows underneath them.
        $eventIds = $event
            ? [$event->id]
            : $brandEvents->pluck('event_id')->filter()->unique()->values()->all();

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

        return $this->payload('Operational Documents', $event, $headings, $rows);
    }

    /**
     * Directory of events so a spreadsheet owner can look up the event id their
     * scoped feed needs. The trailing URL columns are ready to paste straight
     * into the Apps Script CONFIG.
     */
    public function events(): JsonResponse
    {
        $events = Event::query()
            ->with('project:id,name,username')
            ->withCount(['brandEvents', 'orders'])
            // Postgres sorts NULLs first on DESC, which would float undated
            // events above the upcoming ones.
            ->orderByRaw('start_date DESC NULLS LAST')
            ->orderByDesc('id')
            ->get();

        $headings = [
            'ID', 'ULID', 'Event Title', 'Edition', 'Slug',
            'Project ID', 'Project Name', 'Project Username',
            'Status', 'Visibility', 'Active',
            'Start Date', 'End Date', 'Location', 'Hall',
            'Brand Events Count', 'Orders Count',
            'Brands Sheet URL', 'Brand Events Sheet URL', 'Orders Sheet URL', 'Operational Documents Sheet URL',
        ];

        $rows = $events->map(fn (Event $event) => [
            $event->id,
            $event->ulid,
            $event->title,
            $event->edition_number,
            $event->slug,
            $event->project_id,
            $event->project?->name ?? '-',
            $event->project?->username ?? '-',
            Str::title(str_replace('_', ' ', $event->status ?? '-')),
            Str::title(str_replace('_', ' ', $event->visibility ?? '-')),
            $event->is_active ? 'Yes' : 'No',
            SheetFormatting::dateTime($event->start_date),
            SheetFormatting::dateTime($event->end_date),
            $event->location ?? '-',
            $event->hall ?? '-',
            (int) $event->brand_events_count,
            (int) $event->orders_count,
            route('sheets.event.brands', $event),
            route('sheets.event.brand-events', $event),
            route('sheets.event.orders', $event),
            route('sheets.event.operational-documents', $event),
        ])->toArray();

        return response()->json($this->payload('Events', null, $headings, $rows));
    }

    /**
     * Uniform feed envelope. Scoped feeds carry the event name in the title so
     * a spreadsheet shows which event it is pulling.
     *
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function payload(string $title, ?Event $event, array $headings, array $rows): array
    {
        return [
            'title' => $event ? "{$title} - {$event->title}" : $title,
            'headings' => $headings,
            'rows' => $rows,
            'updated_at' => now()->toIso8601String(),
        ];
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
            // Order-scoped only. Anything with an `order_item_id` belongs to a
            // single line and is reported there, in `Item Adjustment Note`;
            // listing it here too would double it up on every row of the order.
            ->filter(fn (AppliedAdjustment $adjustment) => $adjustment->order_item_id === null)
            ->map(function (AppliedAdjustment $adjustment) {
                $reason = $adjustment->rule_snapshot['reason'] ?? $adjustment->label;

                if ($adjustment->voided_at !== null) {
                    return "{$reason} (voided: ".($adjustment->void_reason ?: 'no reason').')';
                }

                return $reason;
            })
            ->filter()
            ->implode('; ') ?: '-';
    }

    /**
     * Labels of the live adjustments on one order item. Voided ones are already
     * filtered out by the caller, so unlike `adjustmentReason()` this never
     * annotates - a line either carries an adjustment or it does not.
     *
     * @param  Collection<int, AppliedAdjustment>  $adjustments
     */
    private function itemAdjustmentNote(Collection $adjustments): string
    {
        return $adjustments
            ->map(fn (AppliedAdjustment $adjustment) => $adjustment->rule_snapshot['reason'] ?? $adjustment->label)
            ->filter()
            ->implode('; ') ?: '-';
    }

    /**
     * A sane name for a single downloaded image. Media library stores an
     * on-disk `file_name` that can carry two extensions ("SB-1.jpg.jpeg") when
     * the uploaded name already had one and the normalised mime type disagreed;
     * this rebuilds it from the original name plus the real extension.
     */
    private function downloadFileName(Media $media): string
    {
        $name = (string) $media->name;
        $extension = (string) $media->extension;

        if ($extension === '' || str_ends_with(mb_strtolower($name), '.'.mb_strtolower($extension))) {
            return $name !== '' ? $name : $media->file_name;
        }

        return preg_replace('/\.(jpe?g|png|webp|gif|svg|avif)$/i', '', $name).'.'.$extension;
    }

    /**
     * Download URL for the brand-event's promotion post images, or '-' when it
     * has none. The token is embedded so the cell is clickable straight out of
     * the spreadsheet - which already holds the same token in its feed URLs, so
     * this exposes nothing new.
     */
    private function promotionImagesLink(BrandEvent $brandEvent): string
    {
        if ((int) ($brandEvent->promotion_post_images_count ?? 0) === 0) {
            return '-';
        }

        return route('sheets.brand-events.promotion-images', [
            'brandEvent' => $brandEvent->id,
            'token' => config('services.sheets.api_token'),
        ]);
    }

    /**
     * Money and rates as real numbers. The `decimal:2` casts hand back strings
     * ("6000000.00"), which land in the spreadsheet next to genuinely numeric
     * columns and make a sheet that mixes two types in one row.
     */
    private static function money(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
