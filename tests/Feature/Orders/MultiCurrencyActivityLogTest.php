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
use App\Services\Order\OrderSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['IDR' => 16000],
        'fetched_at' => now(),
    ]);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'settings' => ['tax_rate' => 11, 'tax_rate_usd' => 10],
    ]);
    $category = EventProductCategory::factory()->create(['event_id' => $this->event->id]);
    $this->product = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category_id' => $category->id,
        'price' => 1000000,
        'price_usd' => 100,
    ]);
    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $this->event->id,
        'currency_override' => 'USD',
    ]);
});

function makeUsdOrder(): Order
{
    return app(OrderSubmissionService::class)->create(test()->brandEvent, [
        ['event_product_id' => test()->product->id, 'quantity' => 2],
    ]);
}

it('does not emit a "total 0 to X" update log when an order is created', function () {
    $order = makeUsdOrder();

    $activities = Activity::query()
        ->where('subject_type', $order->getMorphClass())
        ->where('subject_id', $order->id)
        ->get();

    // Only the "created" event; no noisy recalculate "updated" entry.
    expect($activities)->toHaveCount(1);
    expect($activities->first()->event)->toBe('created');
});

it('formats a USD order money change with a dollar sign in the activity log', function () {
    $order = makeUsdOrder();

    // A genuine later change to the order total is logged.
    $order->update(['total' => 5000]);

    $user = User::factory()->create();
    $user->assignRole('master');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs')->assertSuccessful();

    $newValues = collect($response->json('data'))
        ->flatMap(fn ($entry) => collect($entry['changes'] ?? []))
        ->filter(fn ($change) => $change['field'] === 'total')
        ->pluck('new')
        ->all();

    expect($newValues)->toContain('$5,000.00');
    expect(collect($newValues)->every(fn ($v) => ! str_starts_with((string) $v, 'Rp')))->toBeTrue();
});
