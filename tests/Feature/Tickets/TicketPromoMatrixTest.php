<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->event = Event::factory()->create(['tickets_enabled' => true]);

    // A paid ticket: Rp60.000, unlimited stock + quantity so qty=20 is allowed.
    $this->ticket = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'stock' => null,
        'min_quantity' => 1,
        'max_quantity' => null,
    ]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id,
        'price' => 60000,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $this->purchase = app(TicketPurchaseService::class);
});

/**
 * Place an order for $qty of the test ticket, optionally with a promo code.
 */
function buyTickets(int $qty, ?string $promo = null, string $email = 'buyer@example.com'): TicketOrder
{
    return test()->purchase->createOrder([
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => $email,
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => test()->ticket->id, 'quantity' => $qty]],
        'promo_code' => $promo,
    ]);
}

function makePromo(string $code, PromotionRule $rule): void
{
    // A ticket promo rule must target the TicketOrder morph type, otherwise the
    // engine rejects it with NOT_APPLICABLE_TO_PURCHASE_TYPE.
    $rule->update(['target_types' => ['TicketOrder']]);

    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => $code,
        'event_id' => test()->event->id,
    ]);
}

it('charges the full price with no promo', function (int $qty, float $expectedTotal) {
    $order = buyTickets($qty);

    expect((float) $order->subtotal)->toBe($expectedTotal)
        ->and((float) $order->discount_amount)->toBe(0.0)
        ->and((float) $order->total)->toBe($expectedTotal)
        ->and($order->attendees()->count())->toBe($qty)
        ->and($order->status)->toBe(TicketOrderStatus::PendingPayment);
})->with([
    'quantity 1' => [1, 60000.0],
    'quantity 20' => [20, 1200000.0],
]);

it('applies a percentage discount', function (int $qty, float $pct, float $expectedDiscount, float $expectedTotal) {
    makePromo('PCT', PromotionRule::factory()->discount()->percentage($pct)->create());

    $order = buyTickets($qty, 'PCT');

    expect((float) $order->discount_amount)->toBe($expectedDiscount)
        ->and((float) $order->total)->toBe($expectedTotal)
        ->and($order->promo_code_applied)->toBe('PCT');
})->with([
    '50% off qty 1' => [1, 50, 30000.0, 30000.0],
    '50% off qty 20' => [20, 50, 600000.0, 600000.0],
    '100% off qty 1' => [1, 100, 60000.0, 0.0],
    '100% off qty 20' => [20, 100, 1200000.0, 0.0],
]);

it('confirms a 100%-off order immediately as free (no payment needed)', function () {
    makePromo('FREE100', PromotionRule::factory()->discount()->percentage(100)->create());

    $order = buyTickets(2, 'FREE100');

    expect((float) $order->total)->toBe(0.0)
        ->and($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($order->isFree())->toBeTrue()
        ->and($order->attendees()->count())->toBe(2);
});

it('applies a fixed-amount discount', function () {
    makePromo('CUT20K', PromotionRule::factory()->discount()->fixedAmount(20000)->create());

    $order = buyTickets(1, 'CUT20K');

    expect((float) $order->discount_amount)->toBe(20000.0)
        ->and((float) $order->total)->toBe(40000.0);
});

it('never discounts below zero with a fixed amount larger than the subtotal', function () {
    makePromo('CUT999K', PromotionRule::factory()->discount()->fixedAmount(999000)->create());

    $order = buyTickets(1, 'CUT999K');

    expect((float) $order->total)->toBeGreaterThanOrEqual(0.0)
        ->and((float) $order->discount_amount)->toBeLessThanOrEqual(60000.0);
});

it('applies buy-1-get-1 (discount equals one free ticket)', function () {
    makePromo('BOGO11', PromotionRule::factory()->discount()->buyXGetY(1, 1)->create());

    // Buyer pays for 2; BOGO grants 1 free -> discount of one ticket.
    $order = buyTickets(2, 'BOGO11');

    expect((float) $order->subtotal)->toBe(120000.0)
        ->and((float) $order->discount_amount)->toBe(60000.0)
        ->and((float) $order->total)->toBe(60000.0);
});

it('applies buy-2-get-1', function () {
    makePromo('BOGO21', PromotionRule::factory()->discount()->buyXGetY(2, 1)->create());

    // Buyer pays for 3; buy-2-get-1 grants 1 free -> discount of one ticket.
    $order = buyTickets(3, 'BOGO21');

    expect((float) $order->subtotal)->toBe(180000.0)
        ->and((float) $order->discount_amount)->toBe(60000.0)
        ->and((float) $order->total)->toBe(120000.0);
});

it('previews a percentage discount before the order is placed', function () {
    makePromo('PRE50', PromotionRule::factory()->discount()->percentage(50)->create());

    $preview = $this->purchase->previewCart(
        $this->event,
        [['ticket_id' => $this->ticket->id, 'quantity' => 1]],
        'PRE50',
    );

    expect($preview['discount'])->toBe(30000.0)
        ->and($preview['total'])->toBe(30000.0)
        ->and($preview['promo']['code'])->toBe('PRE50');
});

it('previews a buy-1-get-1 discount before the order is placed', function () {
    makePromo('PREBOGO', PromotionRule::factory()->discount()->buyXGetY(1, 1)->create());

    $preview = $this->purchase->previewCart(
        $this->event,
        [['ticket_id' => $this->ticket->id, 'quantity' => 2]],
        'PREBOGO',
    );

    expect($preview['discount'])->toBe(60000.0)
        ->and($preview['total'])->toBe(60000.0);
});

it('returns a promo error in the preview for an invalid code', function () {
    $preview = $this->purchase->previewCart(
        $this->event,
        [['ticket_id' => $this->ticket->id, 'quantity' => 1]],
        'NOPECODE',
    );

    expect($preview['promo']['error_code'])->toBe('INVALID_CODE')
        ->and($preview['discount'])->toBe(0.0);
});

it('rejects an unknown promo code', function () {
    expect(fn () => buyTickets(1, 'NOPECODE'))->toThrow(ValidationException::class);
});

it('enforces a per-code usage limit', function () {
    $rule = PromotionRule::factory()->discount()->percentage(50)->create(['target_types' => ['TicketOrder']]);
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'ONCE',
        'event_id' => $this->event->id,
        'usage_limit' => 1,
    ]);

    buyTickets(1, 'ONCE', 'a@example.com');

    expect(fn () => buyTickets(1, 'ONCE', 'b@example.com'))->toThrow(ValidationException::class);
});
