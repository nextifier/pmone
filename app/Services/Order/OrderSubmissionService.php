<?php

namespace App\Services\Order;

use App\Mail\OrderConfirmationMail;
use App\Mail\OrderSubmittedMail;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\User;
use App\Services\Currency\CurrencyResolver;
use App\Services\Pricing\PricingService;
use App\Services\Promotion\PenaltyService;
use App\Services\Promotion\PromoCodeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderSubmissionService
{
    public function __construct(
        private PenaltyService $penaltyService,
        private PromoCodeService $promoCodeService,
        private PricingService $pricingService,
        private CurrencyResolver $currencyResolver,
    ) {}

    /**
     * Resolve the current order period for an event: normal, onsite, or (when
     * outside every window) it defaults to normal_order.
     */
    public function determinePeriod(Event $event, CarbonInterface $now): string
    {
        if ($event->normal_order_opens_at && $event->normal_order_closes_at
            && $now->between($event->normal_order_opens_at, $event->normal_order_closes_at)) {
            return 'normal_order';
        }

        if ($event->onsite_order_opens_at && $event->onsite_order_closes_at
            && $now->between($event->onsite_order_opens_at, $event->onsite_order_closes_at)) {
            return 'onsite_order';
        }

        return 'normal_order';
    }

    /**
     * Create an order for a brand event. Prices are computed from active event
     * products; onsite penalties and an optional promo code are applied, then
     * totals are recalculated and persisted.
     *
     * @param  array<int, array{event_product_id: int, quantity: int, notes?: ?string}>  $items
     * @param  array{notes?: ?string, internal_notes?: ?string, promo_code?: ?string, source?: string, user?: ?User, promo_email?: ?string}  $options
     */
    public function create(BrandEvent $brandEvent, array $items, array $options = []): Order
    {
        $event = $brandEvent->event;
        $settings = $event->settings ?? [];

        // Currency + reporting rate are resolved server-side (never from the
        // request) and snapshot onto the order. A missing rate for a foreign
        // currency throws a RuntimeException that controllers turn into a 422.
        $currency = $this->currencyResolver->resolveForBrandEvent($brandEvent);
        $exchangeRateToIdr = $this->currencyResolver->exchangeRateToIdr($currency);

        $taxRate = $currency === 'USD'
            ? (float) ($settings['tax_rate_usd'] ?? $settings['tax_rate'] ?? 11)
            : (float) ($settings['tax_rate'] ?? 11);

        $productIds = collect($items)->pluck('event_product_id');
        $products = EventProduct::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->unique()->count()) {
            throw new \RuntimeException('Some products are no longer available.');
        }

        $products->loadMissing('media');

        $orderPeriod = $this->determinePeriod($event, now());
        $source = $options['source'] ?? 'exhibitor';
        $user = $options['user'] ?? null;

        return DB::transaction(function () use ($items, $brandEvent, $products, $currency, $exchangeRateToIdr, $taxRate, $orderPeriod, $source, $options, $user) {
            $subtotal = 0;
            $itemsData = [];

            // Item unit_price uses the BASE product price in the order currency.
            // Any onsite-period penalty is added afterwards as a separate
            // AppliedAdjustment.
            foreach ($items as $item) {
                $product = $products[$item['event_product_id']];

                // Guard behind the USD-catalog filter: reject the whole order if a
                // USD order includes a product without a manual USD price.
                if ($currency === 'USD' && $product->price_usd === null) {
                    throw new \RuntimeException("Product \"{$product->name}\" is not available for USD orders.");
                }

                $rawPrice = $currency === 'USD' ? $product->price_usd : $product->price;
                $unitPrice = round((float) $rawPrice, 2);
                $totalPrice = $unitPrice * $item['quantity'];
                $subtotal += $totalPrice;

                $itemsData[] = [
                    'event_product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'product_name' => $product->name,
                    'product_image_url' => $product->product_image['md'] ?? $product->product_image['url'] ?? null,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'total_price' => $totalPrice,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $order = Order::create([
                'brand_event_id' => $brandEvent->id,
                'operational_status' => 'submitted',
                'order_period' => $orderPeriod,
                'source' => $source,
                'notes' => $options['notes'] ?? null,
                'internal_notes' => $options['internal_notes'] ?? null,
                'subtotal' => $subtotal,
                'currency' => $currency,
                'exchange_rate_to_idr' => $exchangeRateToIdr,
                'tax_rate' => $taxRate,
                'tax_amount' => 0,
                'total' => 0,
                'submitted_at' => now(),
            ]);

            $order->items()->createMany($itemsData);

            // Evaluate auto-triggered penalty rules (seeded from Event.onsite_penalty_rate).
            $this->penaltyService->evaluateAndApply($order);

            // Apply promo code + finalize pricing. Both run as part of creating
            // the order, so they are wrapped in withoutLogs: the "created"
            // activity already records the order. Logging these would emit
            // misleading "updated total: Rp0 -> ..." entries at creation.
            // Throws ValidationException on an invalid code, aborting the transaction.
            activity()->withoutLogs(function () use ($options, $order, $user): void {
                if (! empty($options['promo_code'])) {
                    $email = $options['promo_email'] ?? $user?->email ?? '';
                    $this->promoCodeService->applyByCode(
                        (string) $options['promo_code'],
                        $order->fresh(['items', 'adjustments.promotionRule', 'brandEvent']),
                        (string) $email,
                        $user?->id,
                    );
                    $order->forceFill([
                        'promo_code_applied' => strtoupper(trim((string) $options['promo_code'])),
                    ])->save();
                }

                // Final recalculate persists discount/penalty/tax/total.
                $this->pricingService->recalculateAndPersist($order->fresh(['adjustments', 'brandEvent']));
            });

            return $order->fresh(['adjustments']);
        });
    }

    /**
     * Dispatch order emails. Internal notification emails go to the event's
     * operational addresses; the exhibitor confirmation goes to brand members,
     * the company email, and (optionally) the acting user.
     */
    public function sendEmails(
        Order $order,
        Event $event,
        Brand $brand,
        ?User $actor = null,
        bool $notifyInternal = true,
        bool $confirmationToBrand = true,
    ): void {
        try {
            $settings = $event->settings ?? [];
            $notificationEmails = $settings['notification_emails'] ?? [];

            if ($notifyInternal && ! empty($notificationEmails)) {
                foreach ($notificationEmails as $email) {
                    Mail::to($email)->queue(new OrderSubmittedMail($order, $event, $brand));
                }
            }

            if (! $confirmationToBrand) {
                return;
            }

            $recipients = collect($brand->recipientEmails());
            if ($actor?->email) {
                $recipients->push($actor->email);
            }

            $recipients
                ->filter(fn ($email) => is_string($email) && trim($email) !== '')
                ->map(fn ($email) => trim($email))
                ->unique(fn ($email) => strtolower($email))
                ->each(fn ($email) => Mail::to($email)->queue(new OrderConfirmationMail($order, $event, $brand)));
        } catch (\Exception $e) {
            logger()->warning('Failed to send order emails', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
