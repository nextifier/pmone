<?php

use App\Mail\OrderConfirmationMail;
use App\Mail\OrderSubmittedMail;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web'])
        ->syncPermissions(['orders.read']);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'settings' => ['tax_rate' => 11, 'notification_emails' => ['ops@test.com']],
    ]);

    $this->brand = Brand::factory()->create(['company_email' => 'brand@test.com']);
    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'booth_type' => 'raw_space',
    ]);

    $category = EventProductCategory::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Power',
    ]);
    $this->product = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category_id' => $category->id,
        'name' => 'Power 2200W',
        'price' => 1500000,
    ]);

    $this->storeUrl = "/api/projects/{$this->project->username}/events/{$this->event->slug}/orders";
});

it('lets staff create a manual order with correct pricing', function () {
    $response = $this->actingAs($this->staff)->postJson($this->storeUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [
            ['event_product_id' => $this->product->id, 'quantity' => 2, 'notes' => 'Near entrance'],
        ],
        'notes' => 'Handled by ops desk',
        'internal_notes' => 'Called the exhibitor to confirm',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.operational_status', 'submitted');

    $order = Order::firstOrFail();
    expect($order->source)->toBe('staff');
    expect($order->created_by)->toBe($this->staff->id);
    expect($order->internal_notes)->toBe('Called the exhibitor to confirm');
    expect($order->subtotal)->toBe('3000000.00');
    expect($order->tax_amount)->toBe('330000.00');
    expect($order->total)->toBe('3330000.00');
    expect($order->items)->toHaveCount(1);
});

it('applies the onsite penalty automatically during the onsite period', function () {
    $this->event->update([
        'onsite_order_opens_at' => Carbon::now()->subDay(),
        'onsite_order_closes_at' => Carbon::now()->addDay(),
        'onsite_penalty_rate' => 25,
    ]);

    $this->actingAs($this->staff)->postJson($this->storeUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertStatus(201);

    $order = Order::firstOrFail()->fresh(['adjustments']);
    expect($order->order_period)->toBe('onsite_order');
    expect((float) $order->penalty_amount)->toBeGreaterThan(0);
    expect($order->adjustments)->not->toBeEmpty();
});

it('rolls back when the promo code is invalid', function () {
    $this->actingAs($this->staff)->postJson($this->storeUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
        'promo_code' => 'DOES-NOT-EXIST',
    ])->assertStatus(422);

    expect(Order::count())->toBe(0);
});

it('sends the exhibitor confirmation by default but not the internal email', function () {
    $this->actingAs($this->staff)->postJson($this->storeUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertStatus(201);

    Mail::assertQueued(OrderConfirmationMail::class);
    Mail::assertNotQueued(OrderSubmittedMail::class);
});

it('skips the confirmation email when send_confirmation_email is false', function () {
    $this->actingAs($this->staff)->postJson($this->storeUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
        'send_confirmation_email' => false,
    ])->assertStatus(201);

    Mail::assertNotQueued(OrderConfirmationMail::class);
    Mail::assertNotQueued(OrderSubmittedMail::class);
});

it('forbids users without orders.create', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('exhibitor');

    $this->actingAs($viewer)->postJson($this->storeUrl, [
        'brand_event_id' => $this->brandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertStatus(403);
});

it('rejects a brand event that belongs to another event', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $otherBrandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $otherEvent->id,
    ]);

    $this->actingAs($this->staff)->postJson($this->storeUrl, [
        'brand_event_id' => $otherBrandEvent->id,
        'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertStatus(404);
});

it('returns catalog and pricing context for the manual order form', function () {
    $response = $this->actingAs($this->staff)->getJson("{$this->storeUrl}/create-info?brand_event_id={$this->brandEvent->id}")
        ->assertSuccessful();

    $response->assertJsonPath('data.tax_rate', 11)
        ->assertJsonPath('data.brand_event.id', $this->brandEvent->id)
        ->assertJsonStructure(['data' => ['products_by_category', 'current_period', 'penalty_rate', 'brand_event']]);
});
