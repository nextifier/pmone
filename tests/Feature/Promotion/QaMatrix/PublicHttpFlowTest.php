<?php

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Models\ApiConsumer;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->scenario = qaScenario();
    $this->scenario['hotel']->update(['tax_percentage' => 0]);

    $this->apiConsumer = ApiConsumer::factory()->create(['is_active' => true]);
    $this->headers = ['X-API-Key' => $this->apiConsumer->api_key];
});

// =====================================================
// K1. preview-pricing endpoint
// =====================================================
it('QA-K1: preview-pricing with promo_code returns valid + discount', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'PRV10']);

    $response = $this->postJson('/api/public/reservations/preview-pricing', [
        'hotel_id' => $this->scenario['hotel']->id,
        'event_id' => $this->scenario['event']->id,
        'guest_email' => 'qa@example.com',
        'items' => [[
            'room_type_id' => $this->scenario['room']->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
        'promo_code' => 'PRV10',
    ], $this->headers);

    $response->assertOk()
        ->assertJsonPath('data.promo_validation.valid', true)
        ->assertJsonPath('data.pricing.discount_amount', 100_000);
});

it('QA-K1: preview-pricing with invalid code returns valid=false', function () {
    $response = $this->postJson('/api/public/reservations/preview-pricing', [
        'hotel_id' => $this->scenario['hotel']->id,
        'event_id' => $this->scenario['event']->id,
        'guest_email' => 'qa@example.com',
        'items' => [[
            'room_type_id' => $this->scenario['room']->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
        'promo_code' => 'NONEXISTENT',
    ], $this->headers);

    $response->assertOk()
        ->assertJsonPath('data.promo_validation.valid', false);
});

// =====================================================
// K2. /api/public/promo-codes/validate
// =====================================================
it('QA-K2: validate endpoint returns DTO shape', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'VALONE']);

    $response = $this->postJson('/api/public/promo-codes/validate', [
        'code' => 'VALONE',
        'email' => 'qa@example.com',
        'target_type' => 'Reservation',
        'payload' => [
            'hotel_id' => $this->scenario['hotel']->id,
            'event_id' => $this->scenario['event']->id,
            'items' => [[
                'room_type_id' => $this->scenario['room']->id,
                'check_in_date' => '2026-07-10',
                'check_out_date' => '2026-07-11',
                'qty' => 1,
            ]],
        ],
    ], $this->headers);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['valid', 'error_code', 'message', 'preview_discount', 'preview_total', 'rule', 'code'],
        ]);
});

// =====================================================
// K3. POST /api/public/reservations with valid promo
// =====================================================
it('QA-K3: create reservation with valid promo records adjustment + usage', function () {
    Queue::fake();

    $rule = PromotionRule::factory()->percentage(10)->create([
        'target_types' => ['Reservation'],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'CREATE10']);

    $xendit = Mockery::mock(XenditService::class);
    $xendit->shouldReceive('createInvoice')->andReturn(['invoice_id' => 'inv-1', 'invoice_url' => 'https://x']);
    $xendit->shouldReceive('gateway')->andReturn(null);
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->scenario['hotel']->id,
        'event_id' => $this->scenario['event']->id,
        'guest_name' => 'Public Guest',
        'guest_email' => 'public@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234567890',
        'items' => [[
            'room_type_id' => $this->scenario['room']->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
        'promo_code' => 'CREATE10',
        'accept_terms' => true,
    ], $this->headers);

    $response->assertCreated();

    $reservation = Reservation::where('guest_email', 'public@example.com')->first();
    expect($reservation)->not->toBeNull()
        ->and($reservation->promo_code_applied)->toBe('CREATE10')
        ->and($reservation->adjustments()->count())->toBe(1)
        ->and(PromoCode::where('code', 'CREATE10')->first()->usage_count)->toBe(1);
});

// =====================================================
// K4. POST with invalid promo blocks reservation
// =====================================================
it('QA-K4: create reservation with invalid promo returns 422 and no reservation', function () {
    Queue::fake();

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->scenario['hotel']->id,
        'event_id' => $this->scenario['event']->id,
        'guest_name' => 'Public Guest',
        'guest_email' => 'public@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234567890',
        'items' => [[
            'room_type_id' => $this->scenario['room']->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
        'promo_code' => 'NONEXISTENT',
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422);
    expect(Reservation::where('guest_email', 'public@example.com')->count())->toBe(0);
});

// =====================================================
// K5. 100% discount path -> Complimentary, no Xendit
// =====================================================
it('QA-K5: 100% discount -> Paid + Complimentary, Xendit not called', function () {
    Queue::fake();

    $rule = PromotionRule::factory()->percentage(100)->create([
        'target_types' => ['Reservation'],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'FREE']);

    $xendit = Mockery::mock(XenditService::class);
    $xendit->shouldNotReceive('createInvoice');
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->scenario['hotel']->id,
        'event_id' => $this->scenario['event']->id,
        'guest_name' => 'Free Guest',
        'guest_email' => 'free@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234567890',
        'items' => [[
            'room_type_id' => $this->scenario['room']->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
        'promo_code' => 'FREE',
        'accept_terms' => true,
    ], $this->headers);

    $response->assertCreated();

    $reservation = Reservation::where('guest_email', 'free@example.com')->first();
    expect($reservation->status)->toBe(ReservationStatus::Paid)
        ->and($reservation->payment_method)->toBe(PaymentMethod::Complimentary)
        ->and((float) $reservation->total_amount)->toBe(0.0)
        ->and($reservation->payment_url)->toBeNull();
    Queue::assertPushed(SendBookingReceivedJob::class);
});
