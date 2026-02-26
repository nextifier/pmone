<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
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

    $permissions = [
        'event_products.create', 'event_products.read', 'event_products.update', 'event_products.delete',
        'events.create', 'events.read', 'events.update', 'events.delete',
        'orders.read', 'orders.update',
        'brands.create', 'brands.read', 'brands.update', 'brands.delete',
    ];

    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->syncPermissions(Permission::all());

    $exhibitorRole = Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);
    $exhibitorRole->syncPermissions(['brands.read', 'brands.update']);

    $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $staffRole->syncPermissions(Permission::all());

    // Staff user
    $this->staff = User::factory()->create();
    $this->staff->assignRole('master');

    // Exhibitor user
    $this->exhibitor = User::factory()->create();
    $this->exhibitor->assignRole('exhibitor');

    // Set up project/event/brand/brand_event
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'settings' => ['tax_rate' => 11, 'notification_emails' => ['ops@test.com']],
    ]);

    $this->brand = Brand::factory()->create();
    $this->brand->users()->attach($this->exhibitor->id, ['role' => 'owner']);

    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'booth_type' => 'raw_space',
    ]);

    // Create some products
    $this->product1 = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category' => 'Listrik',
        'name' => 'Listrik 2200W',
        'price' => 1500000,
    ]);

    $this->product2 = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category' => 'Audio',
        'name' => 'Sound System',
        'price' => 3000000,
    ]);
});

// Exhibitor order tests
it('exhibitor can view order form products', function () {
    $this->actingAs($this->exhibitor);

    $response = $this->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/products"
    );

    $response->assertSuccessful()
        ->assertJsonStructure(['data']);
});

it('exhibitor can view order form info', function () {
    $this->actingAs($this->exhibitor);

    $response = $this->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/order-form-info"
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.tax_rate', 11)
        ->assertJsonStructure(['data' => ['order_form_content', 'tax_rate', 'brand_event', 'event', 'brand']]);
});

it('exhibitor can submit an order', function () {
    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        [
            'items' => [
                ['event_product_id' => $this->product1->id, 'quantity' => 2],
                ['event_product_id' => $this->product2->id, 'quantity' => 1, 'notes' => 'Extra cable'],
            ],
            'notes' => 'Please deliver before 9am',
        ]
    );

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonStructure(['data' => ['ulid', 'order_number', 'subtotal', 'tax_amount', 'total', 'items']]);

    // Verify calculations: 1500000*2 + 3000000*1 = 6000000
    $order = Order::first();
    expect($order->subtotal)->toBe('6000000.00');
    expect($order->tax_rate)->toBe('11.00');
    expect($order->tax_amount)->toBe('660000.00');
    expect($order->total)->toBe('6660000.00');

    // Verify order items created
    expect($order->items)->toHaveCount(2);

    // Verify order number format
    expect($order->order_number)->toStartWith('ORD-');
});

it('exhibitor cannot submit an empty order', function () {
    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => []]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

it('exhibitor cannot submit order with invalid product', function () {
    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        [
            'items' => [
                ['event_product_id' => 99999, 'quantity' => 1],
            ],
        ]
    );

    $response->assertStatus(422);
});

it('exhibitor can list their orders', function () {
    $this->actingAs($this->exhibitor);

    Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
    ]);

    $response = $this->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders"
    );

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('exhibitor can view a specific order', function () {
    $this->actingAs($this->exhibitor);

    $order = Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
    ]);

    $response = $this->getJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders/{$order->ulid}"
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.ulid', $order->ulid);
});

// Staff order management tests
it('staff can list orders for an event', function () {
    $this->actingAs($this->staff);

    Order::factory()->count(2)->create([
        'brand_event_id' => $this->brandEvent->id,
    ]);

    $response = $this->getJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders"
    );

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 2);
});

it('staff can view order detail', function () {
    $this->actingAs($this->staff);

    $order = Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
    ]);

    $response = $this->getJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}"
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.ulid', $order->ulid);
});

it('staff can update order status', function () {
    $this->actingAs($this->staff);

    $order = Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
        'status' => 'submitted',
    ]);

    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/status",
        ['status' => 'confirmed']
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'confirmed');

    $order->refresh();
    expect($order->status)->toBe('confirmed');
    expect($order->confirmed_at)->not->toBeNull();
});

it('staff cannot set invalid order status', function () {
    $this->actingAs($this->staff);

    $order = Order::factory()->create([
        'brand_event_id' => $this->brandEvent->id,
    ]);

    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/status",
        ['status' => 'invalid_status']
    );

    $response->assertStatus(422);
});

it('generates unique order numbers', function () {
    $this->actingAs($this->exhibitor);

    // Submit two orders
    $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [['event_product_id' => $this->product1->id, 'quantity' => 1]]]
    );

    $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [['event_product_id' => $this->product2->id, 'quantity' => 1]]]
    );

    $orders = Order::all();
    expect($orders)->toHaveCount(2);
    expect($orders[0]->order_number)->not->toBe($orders[1]->order_number);
});

it('snapshots product data in order items', function () {
    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [['event_product_id' => $this->product1->id, 'quantity' => 1]]]
    );

    $response->assertStatus(201);

    $order = Order::first();
    $item = $order->items->first();

    expect($item->product_name)->toBe('Listrik 2200W');
    expect($item->product_category)->toBe('Listrik');
    expect($item->unit_price)->toBe('1500000.00');
});

// Discount tests
it('staff can apply percentage discount to order', function () {
    $this->actingAs($this->exhibitor);

    // Submit an order first
    $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $this->product1->id, 'quantity' => 2],
            ['event_product_id' => $this->product2->id, 'quantity' => 1],
        ]]
    )->assertStatus(201);

    $order = Order::first();
    // subtotal = 1500000*2 + 3000000 = 6000000

    $this->actingAs($this->staff);

    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/discount",
        ['discount_type' => 'percentage', 'discount_value' => 10]
    );

    $response->assertSuccessful();

    $order->refresh();
    expect($order->discount_type)->toBe('percentage');
    expect($order->discount_value)->toBe('10.00');
    expect($order->discount_amount)->toBe('600000.00'); // 6000000 * 10%
    // tax = (6000000 - 600000) * 11% = 594000
    expect($order->tax_amount)->toBe('594000.00');
    // total = 5400000 + 594000 = 5994000
    expect($order->total)->toBe('5994000.00');
});

it('staff can apply fixed discount to order', function () {
    $this->actingAs($this->exhibitor);

    $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $this->product1->id, 'quantity' => 2],
        ]]
    )->assertStatus(201);

    $order = Order::first();
    // subtotal = 1500000*2 = 3000000

    $this->actingAs($this->staff);

    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/discount",
        ['discount_type' => 'fixed', 'discount_value' => 500000]
    );

    $response->assertSuccessful();

    $order->refresh();
    expect($order->discount_type)->toBe('fixed');
    expect($order->discount_value)->toBe('500000.00');
    expect($order->discount_amount)->toBe('500000.00');
    // tax = (3000000 - 500000) * 11% = 275000
    expect($order->tax_amount)->toBe('275000.00');
    // total = 2500000 + 275000 = 2775000
    expect($order->total)->toBe('2775000.00');
});

it('staff can remove discount from order', function () {
    $this->actingAs($this->exhibitor);

    $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $this->product1->id, 'quantity' => 2],
        ]]
    )->assertStatus(201);

    $order = Order::first();

    $this->actingAs($this->staff);

    // Apply discount first
    $this->patchJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/discount",
        ['discount_type' => 'percentage', 'discount_value' => 10]
    )->assertSuccessful();

    // Remove discount
    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/discount",
        ['discount_type' => null, 'discount_value' => null]
    );

    $response->assertSuccessful();

    $order->refresh();
    expect($order->discount_type)->toBeNull();
    expect($order->discount_value)->toBeNull();
    expect($order->discount_amount)->toBeNull();
    // Back to original: subtotal = 3000000, tax = 330000, total = 3330000
    expect($order->tax_amount)->toBe('330000.00');
    expect($order->total)->toBe('3330000.00');
});

it('fixed discount cannot exceed subtotal', function () {
    $this->actingAs($this->exhibitor);

    $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $this->product1->id, 'quantity' => 1],
        ]]
    )->assertStatus(201);

    $order = Order::first();
    // subtotal = 1500000

    $this->actingAs($this->staff);

    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders/{$order->ulid}/discount",
        ['discount_type' => 'fixed', 'discount_value' => 2000000]
    );

    $response->assertStatus(422);
});

// Deadline tests
it('exhibitor cannot submit order after deadline', function () {
    $this->event->update(['order_form_deadline' => now()->subDay()]);

    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $this->product1->id, 'quantity' => 1],
        ]]
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Order form deadline has passed.');
});

it('exhibitor can submit order before deadline', function () {
    $this->event->update(['order_form_deadline' => now()->addDay()]);

    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $this->product1->id, 'quantity' => 1],
        ]]
    );

    $response->assertStatus(201);
});

it('exhibitor can submit order when no deadline set', function () {
    // Ensure no deadline
    $this->event->update(['order_form_deadline' => null]);

    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [
            ['event_product_id' => $this->product1->id, 'quantity' => 1],
        ]]
    );

    $response->assertStatus(201);
});

it('exhibitor cannot upload promotion post after deadline', function () {
    $this->event->update(['promotion_post_deadline' => now()->subDay()]);

    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/promotion-posts",
        ['caption' => 'Test post']
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Promotion post deadline has passed.');
});

it('exhibitor can upload promotion post before deadline', function () {
    $this->event->update(['promotion_post_deadline' => now()->addDay()]);

    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/promotion-posts",
        ['caption' => 'Test post']
    );

    $response->assertStatus(201);
});

it('snapshots product image url in order items', function () {
    $this->actingAs($this->exhibitor);

    $response = $this->postJson(
        "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/orders",
        ['items' => [['event_product_id' => $this->product1->id, 'quantity' => 1]]]
    );

    $response->assertStatus(201);

    $order = Order::first();
    $item = $order->items->first();

    // Product has no image uploaded so it should be null
    expect($item->product_image_url)->toBeNull();
});
