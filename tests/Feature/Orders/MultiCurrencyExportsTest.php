<?php

use App\Exports\BrandEventsExport;
use App\Exports\EventProductsExport;
use App\Exports\PromotionRulesExport;
use App\Imports\EventProductsImport;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\Order;
use App\Models\PromotionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds a Price (USD) column to the products export', function () {
    $event = Event::factory()->create();
    $category = EventProductCategory::factory()->create(['event_id' => $event->id]);
    $withUsd = EventProduct::factory()->withUsdPrice(45)->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'price' => 670000,
    ]);
    $idrOnly = EventProduct::factory()->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'price' => 500000,
        'price_usd' => null,
    ]);

    $export = new EventProductsExport($event->id);
    $headings = $export->headings();

    expect($headings)->toContain('Price (IDR)')->toContain('Price (USD)');

    $idx = array_search('Price (USD)', $headings, true);
    expect((float) $export->map($withUsd)[$idx])->toBe(45.0);
    expect($export->map($idrOnly)[$idx])->toBeNull();

    // Thousand-separated number formats on the IDR + USD price columns.
    expect($export->columnFormats())->toBe(['E' => '#,##0', 'F' => '#,##0.00']);
});

it('accepts the Price (IDR) export heading on re-import via the price_idr alias', function () {
    $import = new EventProductsImport(Event::factory()->create()->id);

    $prepared = $import->prepareForValidation([
        'category' => 'Electricity',
        'name' => 'Power',
        'price_idr' => 670000,
    ], 0);

    expect($prepared['price'])->toBe('670000');
});

it('adds a Currency column to the promotion rules export', function () {
    $rule = PromotionRule::factory()->create(['currency' => 'USD']);

    $export = new PromotionRulesExport;
    $headings = $export->headings();

    expect($headings)->toContain('Currency');

    $idx = array_search('Currency', $headings, true);
    expect($export->map($rule)[$idx])->toBe('USD');
});

it('shows a dash for a currency-agnostic rule in the export', function () {
    $rule = PromotionRule::factory()->create(['currency' => null]);

    $export = new PromotionRulesExport;
    $idx = array_search('Currency', $export->headings(), true);

    expect($export->map($rule)[$idx])->toBe('-');
});

it('adds an Order Currency column to the brand events export', function () {
    $event = Event::factory()->create();
    $brand = Brand::factory()->country('Singapore')->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'currency_override' => null,
    ]);

    $export = new BrandEventsExport($event->id);
    $headings = $export->headings();

    expect($headings)->toContain('Order Currency');
});

it('exposes currency columns on the Google Sheets orders feed', function () {
    config(['services.sheets.api_token' => 'test-sheets-token']);

    $event = Event::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $event->id,
    ]);
    Order::factory()->create([
        'brand_event_id' => $brandEvent->id,
        'currency' => 'USD',
        'exchange_rate_to_idr' => 16000,
        'total' => 220,
        'total_idr' => 3520000,
    ]);

    $response = $this->getJson('/api/sheets/orders?token=test-sheets-token')
        ->assertSuccessful();

    $headings = $response->json('headings');
    expect($headings)->toContain('Currency')
        ->toContain('Exchange Rate (to IDR)')
        ->toContain('Total (IDR)');

    $currencyIdx = array_search('Currency', $headings, true);
    $totalIdrIdx = array_search('Total (IDR)', $headings, true);

    expect($response->json("rows.0.{$currencyIdx}"))->toBe('USD');
    expect((float) $response->json("rows.0.{$totalIdrIdx}"))->toBe(3520000.0);
});
