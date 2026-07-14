<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\ExchangeRate;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Services\Order\OrderSubmissionService;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['IDR' => 16000],
        'fetched_at' => now(),
    ]);

    $this->event = Event::factory()->create([
        'settings' => ['tax_rate' => 11, 'tax_rate_usd' => 10],
    ]);
    $category = EventProductCategory::factory()->create(['event_id' => $this->event->id]);
    $this->product = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category_id' => $category->id,
        'price' => 1000000,
        'price_usd' => 100,
    ]);

    $usdBrandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $this->event->id,
        'currency_override' => 'USD',
    ]);
    $idrBrandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $this->event->id,
        'currency_override' => 'IDR',
    ]);

    $service = app(OrderSubmissionService::class);
    $this->usdOrder = $service->create($usdBrandEvent, [
        ['event_product_id' => $this->product->id, 'quantity' => 5],
    ])->fresh(['items', 'brandEvent']);
    $this->idrOrder = $service->create($idrBrandEvent, [
        ['event_product_id' => $this->product->id, 'quantity' => 5],
    ])->fresh(['items', 'brandEvent']);

    $this->promo = app(PromoCodeService::class);
});

it('rejects an IDR fixed-amount promo on a USD order', function () {
    $rule = PromotionRule::factory()->fixedAmount(50000)->create(['currency' => 'IDR']);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'IDR-FIXED']);

    $result = $this->promo->validate('IDR-FIXED', $this->usdOrder, 'buyer@test.com');

    expect($result->valid)->toBeFalse();
    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_CURRENCY_MISMATCH);
});

it('accepts a matching USD percentage promo on a USD order', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['currency' => 'USD']);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'USD-PCT']);

    $result = $this->promo->validate('USD-PCT', $this->usdOrder, 'buyer@test.com');

    expect($result->valid)->toBeTrue();
});

it('rejects a legacy null-currency fixed-amount promo on a USD order', function () {
    $rule = PromotionRule::factory()->fixedAmount(50)->create(['currency' => null]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'LEGACY-FIXED']);

    $result = $this->promo->validate('LEGACY-FIXED', $this->usdOrder, 'buyer@test.com');

    expect($result->valid)->toBeFalse();
    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_CURRENCY_MISMATCH);
});

it('accepts a currency-agnostic percentage promo on a USD order', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['currency' => null]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'AGNOSTIC-PCT']);

    $result = $this->promo->validate('AGNOSTIC-PCT', $this->usdOrder, 'buyer@test.com');

    expect($result->valid)->toBeTrue();
});

it('accepts an IDR fixed-amount promo on an IDR order', function () {
    $rule = PromotionRule::factory()->fixedAmount(50000)->create(['currency' => 'IDR']);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'IDR-OK']);

    $result = $this->promo->validate('IDR-OK', $this->idrOrder, 'buyer@test.com');

    expect($result->valid)->toBeTrue();
});

it('keeps the onsite percentage penalty working on a USD order', function () {
    $this->event->update([
        'onsite_order_opens_at' => now()->subDay(),
        'onsite_order_closes_at' => now()->addDay(),
        'onsite_penalty_rate' => 20,
    ]);

    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $this->event->id,
        'currency_override' => 'USD',
    ]);

    $order = app(OrderSubmissionService::class)->create($brandEvent, [
        ['event_product_id' => $this->product->id, 'quantity' => 2],
    ])->fresh(['adjustments']);

    expect($order->currency)->toBe('USD');
    expect($order->order_period)->toBe('onsite_order');
    // 2 * $100 = $200 subtotal, +20% onsite penalty = $40 penalty (percentage is
    // currency-agnostic and applies in the order currency).
    expect((float) $order->penalty_amount)->toBe(40.0);
    expect((float) $order->total_idr)->toBe(round((float) $order->total * 16000, 2));
});
