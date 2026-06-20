<?php

use App\DTOs\Ticketing\AccessCodeValidation;
use App\Enums\Ticketing\AccessCodeStatus;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\AccessCode;
use App\Models\AppliedAdjustment;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Services\Pricing\PricingService;
use App\Services\Ticket\AccessCodeService;
use App\Services\Ticket\TicketPurchaseService;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
    $this->accessCodes = app(AccessCodeService::class);
});

function acTicket(Event $event, float $price, string $visibility = 'public', ?int $stock = null): Ticket
{
    $ticket = Ticket::factory()->create([
        'event_id' => $event->id,
        'stock' => $stock,
        'visibility' => $visibility,
    ]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

function acCode(Event $event, Ticket $ticket, array $attributes = []): AccessCode
{
    $code = AccessCode::factory()->create(array_merge([
        'event_id' => $event->id,
        'code' => 'VIPCODE'.fake()->unique()->numerify('####'),
    ], $attributes));
    $code->unlocks()->attach($ticket->id);

    return $code->load('unlocks');
}

function withGateway(Project $project): void
{
    ProjectPaymentGateway::factory()->create(['project_id' => $project->id, 'mode' => 'test', 'is_active' => true]);
}

// ─── Validation error paths ──────────────────────────────────────────────────

it('rejects an unknown code', function () {
    $v = $this->accessCodes->validate('NOPE', $this->event);
    expect($v->valid)->toBeFalse()->and($v->errorCode)->toBe(AccessCodeValidation::ERROR_INVALID_CODE);
});

it('rejects a revoked code', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['status' => AccessCodeStatus::Revoked]);

    $v = $this->accessCodes->validate($code->code, $this->event);
    expect($v->errorCode)->toBe(AccessCodeValidation::ERROR_REVOKED);
});

it('rejects a not-yet-valid code', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['valid_from' => now()->addDay()]);

    $v = $this->accessCodes->validate($code->code, $this->event);
    expect($v->errorCode)->toBe(AccessCodeValidation::ERROR_NOT_YET_VALID);
});

it('rejects an expired code', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['valid_until' => now()->subDay()]);

    $v = $this->accessCodes->validate($code->code, $this->event);
    expect($v->errorCode)->toBe(AccessCodeValidation::ERROR_EXPIRED);
});

it('rejects a fully used code', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['max_uses' => 2, 'used_count' => 2]);

    $v = $this->accessCodes->validate($code->code, $this->event);
    expect($v->errorCode)->toBe(AccessCodeValidation::ERROR_USAGE_LIMIT_REACHED);
});

it('enforces bind_email server-side', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['bind_email' => 'vip@example.com']);

    expect($this->accessCodes->validate($code->code, $this->event, 'someone@else.com')->errorCode)
        ->toBe(AccessCodeValidation::ERROR_BIND_EMAIL_MISMATCH);
    expect($this->accessCodes->validate($code->code, $this->event, 'vip@example.com')->valid)
        ->toBeTrue();
});

it('enforces bind_phone server-side (digits only)', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['bind_phone' => '+62 812-0000-1111']);

    expect($this->accessCodes->validate($code->code, $this->event, null, '0899')->errorCode)
        ->toBe(AccessCodeValidation::ERROR_BIND_PHONE_MISMATCH);
    // Same number, different formatting still matches.
    expect($this->accessCodes->validate($code->code, $this->event, null, '628120000 1111')->valid)
        ->toBeTrue();
});

it('rejects a cart with a gated ticket the code does not unlock', function () {
    $unlocked = acTicket($this->event, 0, 'code_required');
    $other = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $unlocked);

    $v = $this->accessCodes->validate($code->code, $this->event, null, null, [
        ['ticket_id' => $other->id, 'quantity' => 1],
    ]);
    expect($v->errorCode)->toBe(AccessCodeValidation::ERROR_TICKET_NOT_UNLOCKED);
});

it('rejects quantity over max_qty_per_redemption', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['max_qty_per_redemption' => 2]);

    $v = $this->accessCodes->validate($code->code, $this->event, null, null, [
        ['ticket_id' => $ticket->id, 'quantity' => 3],
    ]);
    expect($v->errorCode)->toBe(AccessCodeValidation::ERROR_QTY_EXCEEDS_REDEMPTION_LIMIT);
});

it('rejects a price-affecting non-stackable code alongside a promo', function () {
    $ticket = acTicket($this->event, 100000, 'code_required');
    $code = acCode($this->event, $ticket, ['price_effect' => 'percentage', 'price_value' => 50]);

    $v = $this->accessCodes->validate($code->code, $this->event, null, null, [], hasPromo: true);
    expect($v->errorCode)->toBe(AccessCodeValidation::ERROR_STACKING_NOT_ALLOWED);
});

it('allows a none-effect code alongside a promo', function () {
    $ticket = acTicket($this->event, 100000, 'code_required');
    $code = acCode($this->event, $ticket);

    $v = $this->accessCodes->validate($code->code, $this->event, null, null, [], hasPromo: true);
    expect($v->valid)->toBeTrue();
});

it('returns the unlocked tickets on success', function () {
    $ticket = acTicket($this->event, 0, 'hidden');
    $code = acCode($this->event, $ticket);

    $v = $this->accessCodes->validate($code->code, $this->event);
    expect($v->valid)->toBeTrue()
        ->and($v->unlocks)->toHaveCount(1)
        ->and($v->unlocks[0]['ticket_id'])->toBe($ticket->id);
});

// ─── Checkout gating ─────────────────────────────────────────────────────────

it('blocks checkout of a gated ticket without a code', function () {
    $ticket = acTicket($this->event, 0, 'code_required');

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);
});

it('allows checkout of a gated ticket with a valid code (free → claim)', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($order->access_code_applied)->toBe($code->code)
        ->and($code->fresh()->used_count)->toBe(1);
});

// ─── Lifecycle: hold / consume / release ─────────────────────────────────────

it('holds then consumes on a free claim', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['max_uses' => 5]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    $redemption = $order->accessCodeRedemptions()->first();
    expect($code->fresh()->used_count)->toBe(1)
        ->and($redemption->redeemed_at)->not->toBeNull(); // consumed on free-confirm
});

it('holds on a paid order then releases on expiry, idempotently', function () {
    withGateway($this->project);
    $ticket = acTicket($this->event, 100000, 'code_required');
    $code = acCode($this->event, $ticket, ['max_uses' => 1]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createGenericInvoice')->andReturn(['invoice_id' => 'inv_1', 'invoice_url' => 'https://pay/inv_1']);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ], $xendit);

    expect($order->status)->toBe(TicketOrderStatus::PendingPayment)
        ->and($code->fresh()->used_count)->toBe(1)
        ->and($order->accessCodeRedemptions()->first()->redeemed_at)->toBeNull();

    $this->service->expireOrder($order);
    $this->service->expireOrder($order->fresh()); // double-expire

    expect($code->fresh()->used_count)->toBe(0)
        ->and($order->accessCodeRedemptions()->first()->voided_at)->not->toBeNull();
});

it('consumes the hold when a paid order is confirmed', function () {
    withGateway($this->project);
    $ticket = acTicket($this->event, 100000, 'code_required');
    $code = acCode($this->event, $ticket, ['max_uses' => 3]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createGenericInvoice')->andReturn(['invoice_id' => 'inv_2', 'invoice_url' => 'https://pay/inv_2']);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ], $xendit);

    $this->service->markAsConfirmed($order, ['id' => 'inv_2']);

    expect($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($code->fresh()->used_count)->toBe(1)
        ->and($order->accessCodeRedemptions()->first()->redeemed_at)->not->toBeNull();
});

it('prevents overuse of a single-use invitation code', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['max_uses' => 1]);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'B', 'buyer_email' => 'b@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);

    expect($code->fresh()->used_count)->toBe(1);
});

it('enforces bind_email at checkout', function () {
    $ticket = acTicket($this->event, 0, 'code_required');
    $code = acCode($this->event, $ticket, ['bind_email' => 'vip@example.com']);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'wrong@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);
});

// ─── Pricing effects ─────────────────────────────────────────────────────────

it('computes set_price / percentage / amount scoped to unlocked lines', function () {
    $ticket = acTicket($this->event, 100000, 'code_required');

    $setPrice = acCode($this->event, $ticket, ['price_effect' => 'set_price', 'price_value' => 25000]);
    $pct = acCode($this->event, $ticket, ['price_effect' => 'percentage', 'price_value' => 40]);
    $amt = acCode($this->event, $ticket, ['price_effect' => 'amount', 'price_value' => 30000]);

    $lines = [['ticket_id' => $ticket->id, 'unit_price' => 100000, 'quantity' => 2]];

    expect($this->accessCodes->computePriceEffectDiscount($setPrice, $lines)['amount'])->toBe(150000.0) // (100k-25k)*2
        ->and($this->accessCodes->computePriceEffectDiscount($pct, $lines)['amount'])->toBe(80000.0) // 40% of 200k
        ->and($this->accessCodes->computePriceEffectDiscount($amt, $lines)['amount'])->toBe(30000.0); // flat
});

it('ignores lines the code does not unlock when pricing', function () {
    $unlocked = acTicket($this->event, 100000, 'code_required');
    $other = acTicket($this->event, 100000, 'public');
    $code = acCode($this->event, $unlocked, ['price_effect' => 'percentage', 'price_value' => 50]);

    $lines = [
        ['ticket_id' => $unlocked->id, 'unit_price' => 100000, 'quantity' => 1],
        ['ticket_id' => $other->id, 'unit_price' => 100000, 'quantity' => 1],
    ];

    expect($this->accessCodes->computePriceEffectDiscount($code, $lines)['amount'])->toBe(50000.0);
});

it('set_price = 0 makes a paid ticket free and confirms as a claim', function () {
    $ticket = acTicket($this->event, 250000, 'code_required');
    $code = acCode($this->event, $ticket, ['price_effect' => 'set_price', 'price_value' => 0]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'VIP', 'buyer_email' => 'vip@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and((float) $order->total)->toBe(0.0)
        ->and((float) $order->discount_amount)->toBe(250000.0);

    $adj = AppliedAdjustment::where('adjustable_id', $order->id)->first();
    expect($adj->access_code_id)->toBe($code->id)
        ->and($adj->promotion_rule_id)->toBeNull()
        ->and($adj->value_type->value)->toBe('fixed_amount');
});

it('keeps the access discount stable across repeated recalculation', function () {
    withGateway($this->project);
    $ticket = acTicket($this->event, 100000, 'code_required');
    $code = acCode($this->event, $ticket, ['price_effect' => 'percentage', 'price_value' => 50]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createGenericInvoice')->andReturn(['invoice_id' => 'inv_3', 'invoice_url' => 'https://pay/inv_3']);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ], $xendit);

    expect((float) $order->discount_amount)->toBe(50000.0)
        ->and((float) $order->total)->toBe(50000.0);

    app(PricingService::class)->recalculateAndPersist($order->fresh(['items', 'adjustments']));
    app(PricingService::class)->recalculateAndPersist($order->fresh(['items', 'adjustments']));

    expect((float) $order->fresh()->discount_amount)->toBe(50000.0);
});

it('rejects a price-affecting code together with a promo at checkout', function () {
    $ticket = acTicket($this->event, 100000, 'code_required');
    $code = acCode($this->event, $ticket, ['price_effect' => 'percentage', 'price_value' => 50]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'promo_code' => 'ANYTHING',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);
});

// ─── Batch generation ────────────────────────────────────────────────────────

it('generates a single shared code with a usage cap', function () {
    $ticket = acTicket($this->event, 0, 'code_required');

    $batch = $this->accessCodes->generateBatch($this->event, [
        'name' => 'Media', 'kind' => 'shared', 'max_uses' => 100,
        'unlocks' => [$ticket->id],
    ]);

    expect($batch->accessCodes)->toHaveCount(1)
        ->and($batch->accessCodes->first()->max_uses)->toBe(100)
        ->and($batch->accessCodes->first()->unlocks)->toHaveCount(1);
});

it('generates N unique single-use invitation codes bound to recipients', function () {
    $ticket = acTicket($this->event, 0, 'hidden');

    $batch = $this->accessCodes->generateBatch($this->event, [
        'name' => 'VIP wave', 'kind' => 'invitation',
        'unlocks' => [$ticket->id],
        'recipients' => [
            ['email' => 'a@e.com', 'name' => 'A'],
            ['email' => 'b@e.com', 'phone' => '0812'],
        ],
    ]);

    expect($batch->accessCodes)->toHaveCount(2)
        ->and($batch->accessCodes->pluck('code')->unique())->toHaveCount(2)
        ->and($batch->accessCodes->every(fn ($c) => $c->max_uses === 1))->toBeTrue()
        ->and($batch->accessCodes->firstWhere('bind_email', 'a@e.com'))->not->toBeNull();
});

// ─── Revoke ──────────────────────────────────────────────────────────────────

it('revoke blocks new checkouts but lets an in-flight paid order confirm', function () {
    withGateway($this->project);
    $ticket = acTicket($this->event, 100000, 'code_required');
    $code = acCode($this->event, $ticket, ['max_uses' => 5]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createGenericInvoice')->andReturn(['invoice_id' => 'inv_r', 'invoice_url' => 'https://pay/inv_r']);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'X', 'buyer_email' => 'x@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ], $xendit);

    $this->accessCodes->revoke($code->fresh(), 'leaked');

    // New order with the revoked code is blocked.
    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Y', 'buyer_email' => 'y@e.com', 'buyer_phone' => '08',
        'access_code' => $code->code,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ], $xendit))->toThrow(HttpException::class);

    // The already-pending order still confirms.
    $this->service->markAsConfirmed($order, ['id' => 'inv_r']);
    expect($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed);
});
