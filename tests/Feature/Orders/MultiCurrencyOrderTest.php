<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    foreach ([
        'orders.create', 'orders.read', 'orders.update',
        'promotions.apply_manual', 'promotions.void_adjustment',
    ] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web'])->syncPermissions(['orders.read']);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $this->exhibitor->assignRole('exhibitor');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'settings' => ['tax_rate' => 11, 'tax_rate_usd' => 10, 'notification_emails' => ['ops@test.com']],
    ]);

    $this->brand = Brand::factory()->create(['company_email' => 'brand@test.com']);
    $this->brand->users()->attach($this->exhibitor->id, ['role' => 'owner']);

    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'booth_type' => 'raw_space',
    ]);

    $category = EventProductCategory::factory()->create(['event_id' => $this->event->id, 'title' => 'Power']);

    $this->product = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category_id' => $category->id,
        'name' => 'Power 2200W',
        'price' => 1500000,
        'price_usd' => 100,
    ]);

    $this->productNoUsd = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category_id' => $category->id,
        'name' => 'Local Only Item',
        'price' => 500000,
        'price_usd' => null,
    ]);

    $this->exhibitorOrdersUrl = "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders";
    $this->staffOrdersUrl = "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders";
});

function seedUsdRate(float $rate = 16000): void
{
    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['IDR' => $rate],
        'fetched_at' => now(),
    ]);
}

it('bills an exhibitor USD order in USD using price_usd and tax_rate_usd', function () {
    seedUsdRate(16000);
    $this->brandEvent->update(['currency_override' => 'USD']);

    $this->actingAs($this->exhibitor)->postJson($this->exhibitorOrdersUrl, [
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 2]],
    ])->assertStatus(201);

    $order = Order::firstOrFail();
    expect($order->currency)->toBe('USD');
    expect((float) $order->exchange_rate_to_idr)->toBe(16000.0);
    // 2 * $100 = $200 subtotal, +10% USD tax = $220 total
    expect((float) $order->subtotal)->toBe(200.0);
    expect((float) $order->tax_rate)->toBe(10.0);
    expect((float) $order->total)->toBe(220.0);
    expect((float) $order->total_idr)->toBe(220.0 * 16000);
    expect((float) $order->items()->first()->unit_price)->toBe(100.0);
});

it('rejects a USD order containing a product without a USD price', function () {
    seedUsdRate();
    $this->brandEvent->update(['currency_override' => 'USD']);

    $this->actingAs($this->exhibitor)->postJson($this->exhibitorOrdersUrl, [
        'items' => [
            ['event_product_id' => $this->product->id, 'quantity' => 1],
            ['event_product_id' => $this->productNoUsd->id, 'quantity' => 1],
        ],
    ])->assertStatus(422);

    expect(Order::count())->toBe(0);
});

it('keeps IDR order behaviour intact with total_idr equal to total and rate 1', function () {
    // No override, default brand country resolves to IDR.
    $this->actingAs($this->exhibitor)->postJson($this->exhibitorOrdersUrl, [
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 2]],
    ])->assertStatus(201);

    $order = Order::firstOrFail();
    expect($order->currency)->toBe('IDR');
    expect((float) $order->exchange_rate_to_idr)->toBe(1.0);
    expect((float) $order->tax_rate)->toBe(11.0);
    // 2 * 1,500,000 = 3,000,000 + 11% = 3,330,000
    expect((float) $order->total)->toBe(3330000.0);
    expect((float) $order->total_idr)->toBe((float) $order->total);
});

it('fails a USD order with a 422 when no exchange rate is available', function () {
    $this->brandEvent->update(['currency_override' => 'USD']);

    $this->actingAs($this->exhibitor)->postJson($this->exhibitorOrdersUrl, [
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertStatus(422);

    expect(Order::count())->toBe(0);
});

it('lets staff create a USD manual order billed in USD', function () {
    seedUsdRate(15500);
    $this->brandEvent->update(['currency_override' => 'USD']);

    $this->actingAs($this->staff)->postJson($this->staffOrdersUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 3]],
    ])->assertStatus(201);

    $order = Order::firstOrFail();
    expect($order->currency)->toBe('USD');
    expect((float) $order->exchange_rate_to_idr)->toBe(15500.0);
    // 3 * $100 = $300, +10% = $330
    expect((float) $order->total)->toBe(330.0);
    expect((float) $order->total_idr)->toBe(330.0 * 15500);
});

it('recomputes total_idr when a manual adjustment changes the total', function () {
    seedUsdRate(16000);
    $this->brandEvent->update(['currency_override' => 'USD']);

    $this->actingAs($this->staff)->postJson($this->staffOrdersUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 2]],
    ])->assertStatus(201);

    $order = Order::firstOrFail();
    $originalTotalIdr = (float) $order->total_idr;
    expect($originalTotalIdr)->toBe(220.0 * 16000);

    // Apply a manual $50 discount adjustment.
    $this->actingAs($this->staff)->postJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/adjustments",
        [
            'mode' => 'manual',
            'kind' => 'discount',
            'value_type' => 'fixed_amount',
            'value' => 50,
        ]
    )->assertSuccessful();

    $order->refresh();
    // total_idr must stay total * rate after the recalculate.
    expect((float) $order->total_idr)->toBe(round((float) $order->total * 16000, 2));
    expect((float) $order->total_idr)->toBeLessThan($originalTotalIdr);
});

it('hides products without a USD price from the USD catalog and reports the currency', function () {
    seedUsdRate();
    $this->brandEvent->update(['currency_override' => 'USD']);

    $response = $this->actingAs($this->exhibitor)->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/products"
    )->assertSuccessful()->assertJsonPath('currency', 'USD');

    $names = collect($response->json('data'))
        ->flatMap(fn ($group) => collect($group['products'])->pluck('name'))
        ->all();

    expect($names)->toContain('Power 2200W');
    expect($names)->not->toContain('Local Only Item');

    // The catalog price for a USD exhibitor is the USD price.
    $prices = collect($response->json('data'))
        ->flatMap(fn ($group) => collect($group['products'])->pluck('price'))
        ->map(fn ($p) => (float) $p)
        ->all();
    expect($prices)->toContain(100.0);
});

it('shows all products with IDR prices for an IDR catalog', function () {
    $response = $this->actingAs($this->exhibitor)->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/products"
    )->assertSuccessful()->assertJsonPath('currency', 'IDR');

    $names = collect($response->json('data'))
        ->flatMap(fn ($group) => collect($group['products'])->pluck('name'))
        ->all();

    expect($names)->toContain('Power 2200W');
    expect($names)->toContain('Local Only Item');
});

it('exposes a currency-aware tax rate on the order form info', function () {
    seedUsdRate();
    $this->brandEvent->update(['currency_override' => 'USD']);

    $this->actingAs($this->exhibitor)->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/order-form-info"
    )->assertSuccessful()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.tax_rate', 10);
});
