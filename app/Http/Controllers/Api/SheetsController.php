<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrandEvent;
use App\Models\Contact;
use App\Models\Event;
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
            'Fascia Name', 'Sales PIC', 'Order Period',
            'Product Name', 'Product Category', 'Qty', 'Unit Price', 'Item Total', 'Item Notes',
            'Subtotal', 'Discount Type', 'Discount Value', 'Discount Amount',
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
                $brandEvent?->sales?->name ?? '-',
                $order->order_period ? ucwords(str_replace('_', ' ', $order->order_period)) : '-',
            ];

            $orderSummary = [
                $order->subtotal,
                $order->discount_type ? ucwords(str_replace('_', ' ', $order->discount_type)) : '-',
                $order->discount_value,
                $order->discount_amount,
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
                            $item->productCategory?->name ?? '-',
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
}
