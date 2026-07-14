<?php

use App\Exports\OrdersExport;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['orders.read', 'orders.update'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web'])->syncPermissions(Permission::all());

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);

    $brand = Brand::factory()->create();
    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    // IDR order: total 5,000,000 -> total_idr 5,000,000
    $this->idrOrder = Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
        'operational_status' => 'submitted',
        'currency' => 'IDR',
        'exchange_rate_to_idr' => 1,
        'subtotal' => 5000000,
        'tax_amount' => 0,
        'total' => 5000000,
        'total_idr' => 5000000,
    ]);

    // USD order: total $2,000 @ 16000 -> total_idr 32,000,000 (bigger in IDR terms,
    // smaller in raw total than the IDR order).
    $this->usdOrder = Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
        'operational_status' => 'submitted',
        'currency' => 'USD',
        'exchange_rate_to_idr' => 16000,
        'subtotal' => 2000,
        'tax_amount' => 0,
        'total' => 2000,
        'total_idr' => 32000000,
    ]);

    $this->indexUrl = "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders";
});

it('sorts total across currencies via total_idr', function () {
    $response = $this->actingAs($this->staff)
        ->getJson("{$this->indexUrl}?sort=-total")
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();

    // USD order ($2,000 -> Rp32,000,000) must outrank the IDR order (Rp5,000,000)
    // even though its raw total is smaller.
    expect($ids[0])->toBe($this->usdOrder->id);
    expect($ids[1])->toBe($this->idrOrder->id);
});

it('exposes currency, rate, and total_idr on the index resource', function () {
    $response = $this->actingAs($this->staff)
        ->getJson("{$this->indexUrl}?sort=-total")
        ->assertSuccessful();

    $first = $response->json('data.0');
    expect($first['currency'])->toBe('USD');
    expect((float) $first['total_idr'])->toBe(32000000.0);
    expect((float) $first['exchange_rate_to_idr'])->toBe(16000.0);
});

it('filters the index by currency', function () {
    $response = $this->actingAs($this->staff)
        ->getJson("{$this->indexUrl}?filter[currency]=USD")
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$this->usdOrder->id]);
});

it('filters the index by an IDR total range against total_idr', function () {
    // Only the USD order (Rp32M) is above 10M.
    $response = $this->actingAs($this->staff)
        ->getJson("{$this->indexUrl}?filter[total_min]=10000000")
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$this->usdOrder->id]);
});

it('defaults an unknown sort field instead of erroring', function () {
    $response = $this->actingAs($this->staff)
        ->getJson("{$this->indexUrl}?sort=notes")
        ->assertSuccessful();

    expect($response->json('data'))->toHaveCount(2);
});

it('filters the global orders endpoint by currency (flat param)', function () {
    $response = $this->actingAs($this->staff)
        ->getJson('/api/orders?currency=USD')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($this->usdOrder->id);
    expect($ids)->not->toContain($this->idrOrder->id);
});

it('includes the multi-currency columns in the orders export', function () {
    $export = new OrdersExport($this->event->id);

    expect($export->headings())
        ->toContain('Currency')
        ->toContain('Exchange Rate (to IDR)')
        ->toContain('Total (IDR)');

    $row = collect($export->map($this->usdOrder->fresh(['brandEvent.brand', 'items'])))
        ->flatten()
        ->all();

    expect($row)->toContain('USD');
    expect($row)->toContain(32000000.0);
});
