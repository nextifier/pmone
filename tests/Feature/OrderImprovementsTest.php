<?php

use App\Mail\Order\OrderDocumentMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $permissions = [
        'events.read', 'events.update',
        'orders.read', 'orders.update',
        'brands.read', 'brands.update',
        'promotions.apply_manual', 'promotions.void_adjustment',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    foreach (['master', 'staff'] as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web'])->syncPermissions(Permission::all());
    }
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web'])
        ->syncPermissions(['brands.read', 'brands.update']);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $this->exhibitor->assignRole('exhibitor');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'settings' => ['tax_rate' => 11, 'notification_emails' => ['ops@test.com']],
    ]);

    $this->brand = Brand::factory()->create(['company_email' => 'company@test.com']);
    $this->brand->users()->attach($this->exhibitor->id, ['role' => 'owner']);

    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'booth_type' => 'raw_space',
        'fascia_name' => 'My Fascia',
        'badge_name' => 'My Badge',
    ]);

    $this->category = EventProductCategory::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Listrik',
    ]);

    $this->product1 = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category_id' => $this->category->id,
        'name' => 'Listrik 2200W',
        'price' => 1500000,
    ]);
    $this->product2 = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category_id' => $this->category->id,
        'name' => 'Sound System',
        'price' => 3000000,
    ]);

    $this->ordersBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders";
});

function submitImprovementOrder($test): Order
{
    $test->actingAs($test->exhibitor);
    $test->postJson(
        "/api/exhibitor/brands/{$test->brand->slug}/events/{$test->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $test->product1->id, 'quantity' => 2],
            ['event_product_id' => $test->product2->id, 'quantity' => 1],
        ]]
    )->assertStatus(201);

    return Order::first();
}

// A: Terms & Conditions
it('persists order_form_content when updating the event', function () {
    $this->actingAs($this->staff);

    $this->putJson("/api/projects/{$this->project->username}/events/{$this->event->slug}", [
        'order_form_content' => '<p>These are the terms.</p>',
    ])->assertSuccessful();

    expect($this->event->fresh()->order_form_content)->toBe('<p>These are the terms.</p>');
});

// B: Per-item adjustment
it('scopes a manual percentage discount to a single order item', function () {
    $order = submitImprovementOrder($this);
    // subtotal = 1.5M*2 + 3M = 6.000.000
    $item = $order->items->firstWhere('event_product_id', $this->product1->id); // 3.000.000

    $this->actingAs($this->staff);
    $this->postJson("{$this->ordersBase}/{$order->ulid}/adjustments", [
        'mode' => 'manual',
        'kind' => 'discount',
        'value_type' => 'percentage',
        'value' => 10,
        'order_item_id' => $item->id,
    ])->assertSuccessful();

    $order->refresh();
    // 10% of the item (3.000.000) = 300.000, NOT 10% of the whole order (600.000)
    expect((float) $order->discount_amount)->toBe(300000.0);

    $adjustment = $order->adjustments()->first();
    expect($adjustment->order_item_id)->toBe($item->id);
});

it('caps a per-item fixed-amount discount at the item amount', function () {
    $order = submitImprovementOrder($this);
    // product1 line amount = 1.5M * 2 = 3.000.000; order subtotal = 6.000.000
    $item = $order->items->firstWhere('event_product_id', $this->product1->id);

    $this->actingAs($this->staff);
    $this->postJson("{$this->ordersBase}/{$order->ulid}/adjustments", [
        'mode' => 'manual',
        'kind' => 'discount',
        'value_type' => 'fixed_amount',
        'value' => 5000000, // bigger than the 3M item, smaller than the 6M order
        'order_item_id' => $item->id,
    ])->assertSuccessful();

    $order->refresh();
    // Clamped to the item amount (3.000.000), NOT the requested 5.000.000
    // nor the order remaining (6.000.000).
    expect((float) $order->discount_amount)->toBe(3000000.0);
});

it('never lets total discount exceed the subtotal with mixed per-item and order-level discounts', function () {
    $order = submitImprovementOrder($this);
    $item = $order->items->firstWhere('event_product_id', $this->product1->id); // 3.000.000

    $this->actingAs($this->staff);
    // Order-level fixed discount close to the whole subtotal.
    $this->postJson("{$this->ordersBase}/{$order->ulid}/adjustments", [
        'mode' => 'manual', 'kind' => 'discount', 'value_type' => 'fixed_amount', 'value' => 5000000,
    ])->assertSuccessful();
    // Plus a large per-item fixed discount on the 3M item.
    $this->postJson("{$this->ordersBase}/{$order->ulid}/adjustments", [
        'mode' => 'manual', 'kind' => 'discount', 'value_type' => 'fixed_amount', 'value' => 5000000,
        'order_item_id' => $item->id,
    ])->assertSuccessful();

    $order->refresh();
    // Total discount can never exceed the subtotal, so the order total stays >= 0.
    expect((float) $order->discount_amount)->toBeLessThanOrEqual(6000000.0);
    expect((float) $order->total)->toBeGreaterThanOrEqual(0.0);
});

it('order-level manual discount still applies to the whole subtotal', function () {
    $order = submitImprovementOrder($this);

    $this->actingAs($this->staff);
    $this->postJson("{$this->ordersBase}/{$order->ulid}/adjustments", [
        'mode' => 'manual', 'kind' => 'discount', 'value_type' => 'percentage', 'value' => 10,
    ])->assertSuccessful();

    $order->refresh();
    expect((float) $order->discount_amount)->toBe(600000.0);
    expect($order->adjustments()->first()->order_item_id)->toBeNull();
});

it('rejects a per-item adjustment for an item not on the order', function () {
    $order = submitImprovementOrder($this);

    $otherOrder = Order::factory()->create(['brand_event_id' => $this->brandEvent->id]);
    $otherItem = $otherOrder->items()->create([
        'event_product_id' => $this->product1->id,
        'category_id' => $this->category->id,
        'product_name' => 'X',
        'unit_price' => 1000,
        'quantity' => 1,
        'total_price' => 1000,
    ]);

    $this->actingAs($this->staff);
    $this->postJson("{$this->ordersBase}/{$order->ulid}/adjustments", [
        'mode' => 'manual', 'kind' => 'discount', 'value_type' => 'percentage', 'value' => 10,
        'order_item_id' => $otherItem->id,
    ])->assertStatus(422);
});

// D: Email to all brand members
it('queues order confirmation to all brand members and company email', function () {
    $member2 = User::factory()->create(['email_verified_at' => now()]);
    $this->brand->users()->attach($member2->id, ['role' => 'member']);

    submitImprovementOrder($this);

    Mail::assertQueued(OrderConfirmationMail::class, fn ($m) => $m->hasTo($this->exhibitor->email));
    Mail::assertQueued(OrderConfirmationMail::class, fn ($m) => $m->hasTo($member2->email));
    Mail::assertQueued(OrderConfirmationMail::class, fn ($m) => $m->hasTo('company@test.com'));
});

// E: Internal notes
it('staff can update internal notes on the order and items', function () {
    $order = submitImprovementOrder($this);
    $item = $order->items->first();

    $this->actingAs($this->staff);
    $this->patchJson("{$this->ordersBase}/{$order->ulid}/internal-notes", [
        'internal_notes' => 'Order staff note',
        'items' => [['id' => $item->id, 'internal_notes' => 'Item staff note']],
    ])->assertSuccessful()
        ->assertJsonPath('data.internal_notes', 'Order staff note');

    expect($order->fresh()->internal_notes)->toBe('Order staff note');
    expect($item->fresh()->internal_notes)->toBe('Item staff note');
});

it('updates a single item note without clearing the order-level note', function () {
    $order = submitImprovementOrder($this);
    $order->update(['internal_notes' => 'Existing order note']);
    $item = $order->items->first();

    $this->actingAs($this->staff);
    $this->patchJson("{$this->ordersBase}/{$order->ulid}/internal-notes", [
        'items' => [['id' => $item->id, 'internal_notes' => 'Just the item']],
    ])->assertSuccessful();

    expect($order->fresh()->internal_notes)->toBe('Existing order note');
    expect($item->fresh()->internal_notes)->toBe('Just the item');
});

it('updates the order note without touching item notes', function () {
    $order = submitImprovementOrder($this);
    $item = $order->items->first();
    $item->update(['internal_notes' => 'Existing item note']);

    $this->actingAs($this->staff);
    $this->patchJson("{$this->ordersBase}/{$order->ulid}/internal-notes", [
        'internal_notes' => 'Just the order',
    ])->assertSuccessful();

    expect($order->fresh()->internal_notes)->toBe('Just the order');
    expect($item->fresh()->internal_notes)->toBe('Existing item note');
});

it('hides internal notes from exhibitors', function () {
    $order = Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
        'internal_notes' => 'secret staff note',
    ]);

    $this->actingAs($this->exhibitor);
    $response = $this->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders/{$order->ulid}"
    );

    $response->assertSuccessful();
    expect($response->json('data'))->not->toHaveKey('internal_notes');
});

// F: Invoice / Receipt upload + send
it('staff can upload an order invoice and email it to brand recipients', function () {
    Storage::fake('public');

    $order = Order::factory()->create(['brand_event_id' => $this->brandEvent->id]);

    $this->actingAs($this->staff);

    $pdf = UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n".str_repeat('a', 512)."\n%%EOF");
    $this->post("{$this->ordersBase}/{$order->ulid}/invoice", ['invoice' => $pdf])
        ->assertSuccessful();

    expect($order->fresh()->hasMedia('invoice'))->toBeTrue();

    $this->postJson("{$this->ordersBase}/{$order->ulid}/send-invoice")->assertSuccessful();

    Mail::assertSent(OrderDocumentMail::class, fn ($m) => $m->type === 'invoice' && $m->hasTo($this->exhibitor->email));
    Mail::assertSent(OrderDocumentMail::class, fn ($m) => $m->hasTo('company@test.com'));
});

it('rejects a non-pdf invoice upload', function () {
    Storage::fake('public');
    $order = Order::factory()->create(['brand_event_id' => $this->brandEvent->id]);

    $this->actingAs($this->staff);
    $this->post("{$this->ordersBase}/{$order->ulid}/invoice", [
        'invoice' => UploadedFile::fake()->create('invoice.txt', 50, 'text/plain'),
    ])->assertStatus(422);
});

it('cannot send an invoice before one is uploaded', function () {
    $order = Order::factory()->create(['brand_event_id' => $this->brandEvent->id]);

    $this->actingAs($this->staff);
    $this->postJson("{$this->ordersBase}/{$order->ulid}/send-invoice")->assertStatus(422);
});

it('staff can upload and send an order receipt (image allowed)', function () {
    Storage::fake('public');
    $order = Order::factory()->create(['brand_event_id' => $this->brandEvent->id]);

    $this->actingAs($this->staff);
    $this->post("{$this->ordersBase}/{$order->ulid}/receipt", [
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertSuccessful();

    expect($order->fresh()->hasMedia('receipt'))->toBeTrue();

    $this->postJson("{$this->ordersBase}/{$order->ulid}/send-receipt")->assertSuccessful();
    Mail::assertSent(OrderDocumentMail::class, fn ($m) => $m->type === 'receipt');
});

// I: Sheets exports
it('orders sheet includes Badge Name and resolves Product Category title', function () {
    config()->set('services.sheets.api_token', 'test-token');

    submitImprovementOrder($this);

    $response = $this->getJson('/api/sheets/orders?token=test-token');
    $response->assertSuccessful();

    $headings = $response->json('headings');
    expect($headings)->toContain('Badge Name');

    $rows = $response->json('rows');
    $catIndex = array_search('Product Category', $headings);
    $badgeIndex = array_search('Badge Name', $headings);

    expect($rows[0][$catIndex])->toBe('Listrik');
    expect($rows[0][$badgeIndex])->toBe('My Badge');
});

it('moves operational documents off the brand-events sheet into their own sheet', function () {
    config()->set('services.sheets.api_token', 'test-token');

    $doc = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Floor Plan',
        'document_type' => 'text_input',
        'blocks_next_step' => false,
    ]);
    EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Rules Agreement',
        'document_type' => 'checkbox_agreement',
        'blocks_next_step' => true,
    ]);

    // Submission keyed exactly like the controller (event_id + booth_identifier).
    $boothIdentifier = $this->brandEvent->booth_number ?: 'be-'.$this->brandEvent->id;
    EventDocumentSubmission::factory()->withTextValue('Hall A - Row 3')->create([
        'event_document_id' => $doc->id,
        'event_id' => $this->event->id,
        'booth_identifier' => $boothIdentifier,
    ]);

    // Brand-events sheet no longer carries any Doc: columns.
    $brandEventsHeadings = $this->getJson('/api/sheets/brand-events?token=test-token')
        ->assertSuccessful()
        ->json('headings');
    $docHeadings = collect($brandEventsHeadings)->filter(fn ($h) => str_starts_with($h, 'Doc: '));
    expect($docHeadings)->toHaveCount(0);

    // The new operational-documents sheet has a row per applicable document,
    // including the blocking event rule.
    $opsResponse = $this->getJson('/api/sheets/operational-documents?token=test-token')
        ->assertSuccessful();
    $opsHeadings = $opsResponse->json('headings');
    $rows = collect($opsResponse->json('rows'));

    $titleCol = array_search('Document Title', $opsHeadings, true);
    $titles = $rows->pluck($titleCol);
    expect($titles)->toContain('Floor Plan');
    expect($titles)->toContain('Rules Agreement');
});
