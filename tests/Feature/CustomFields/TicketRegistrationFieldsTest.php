<?php

use App\Models\ApiConsumer;
use App\Models\Attendee;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ApiConsumer::factory()->create(['api_key' => 'pk_reg_fields']);
    $this->headers = ['X-API-Key' => 'pk_reg_fields'];
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

function regOnSaleTicket(Event $event, float $price = 0): Ticket
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket;
}

function postRegOrder(array $registrationResponses): TestResponse
{
    $ticket = regOnSaleTicket(test()->event);

    return test()->withHeaders(test()->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => 'buyer@example.com',
        'buyer_phone' => '08123',
        'accept_terms' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'registration' => ['responses' => $registrationResponses],
    ]);
}

// ── Admin CRUD (ticket_registration context) ────────────────────────────────

it('lists ticket-registration fields separately from business-matching fields', function () {
    $registration = CustomField::factory()->ticketRegistration($this->event)->create();
    CustomField::factory()->businessMatching($this->event)->create();

    $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/custom-fields?context=ticket_registration")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $registration->id)
        ->assertJsonPath('data.0.context', CustomField::CONTEXT_TICKET_REGISTRATION);

    $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/custom-fields?context=business_matching")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.context', CustomField::CONTEXT_BUSINESS_MATCHING);
});

it('stores a ticket-registration field via the shared endpoint', function () {
    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/custom-fields", [
            'context' => 'ticket_registration',
            'label' => ['en' => 'Company'],
            'type' => 'text',
        ])
        ->assertCreated()
        ->assertJsonPath('data.context', CustomField::CONTEXT_TICKET_REGISTRATION);

    $this->assertDatabaseHas('custom_fields', [
        'fieldable_type' => Event::class,
        'fieldable_id' => $this->event->id,
        'context' => CustomField::CONTEXT_TICKET_REGISTRATION,
    ]);
});

it('reorders ticket-registration fields', function () {
    $a = CustomField::factory()->ticketRegistration($this->event)->create();
    $b = CustomField::factory()->ticketRegistration($this->event)->create();

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/custom-fields/reorder", [
            'context' => 'ticket_registration',
            'orders' => [
                ['id' => $a->id, 'order' => 2],
                ['id' => $b->id, 'order' => 1],
            ],
        ])
        ->assertOk();

    expect($a->fresh()->order_column)->toBe(2)
        ->and($b->fresh()->order_column)->toBe(1);
});

it('404s when the bound field belongs to another event', function () {
    $otherEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $foreign = CustomField::factory()->ticketRegistration($otherEvent)->create();

    $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/custom-fields/{$foreign->id}")
        ->assertNotFound();
});

// ── Public registration-fields endpoint ─────────────────────────────────────

it('returns only active registration fields', function () {
    CustomField::factory()->ticketRegistration($this->event)->create();
    CustomField::factory()->ticketRegistration($this->event)->inactive()->create();

    $this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/registration-fields")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_active', true);
});

it('localizes the label via the locale query parameter', function () {
    CustomField::factory()->ticketRegistration($this->event)->create([
        'label' => ['en' => 'Company', 'id' => 'Perusahaan'],
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/registration-fields?locale=id")
        ->assertOk()
        ->assertJsonPath('data.0.label', 'Perusahaan');
});

it('returns an empty list when the event has no registration fields', function () {
    $this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/registration-fields")
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('busts the tickets cache when a registration field is created', function () {
    ResponseCache::spy();

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/custom-fields", [
            'context' => 'ticket_registration',
            'label' => ['en' => 'Company'],
            'type' => 'text',
        ])
        ->assertCreated();

    ResponseCache::shouldHaveReceived('clear')->with(['tickets']);
});

// ── Checkout registration answers ───────────────────────────────────────────

it('stores valid registration answers on the first attendee', function () {
    $field = CustomField::factory()->ticketRegistration($this->event)->type('text')->create();

    postRegOrder([$field->ulid => 'Acme'])->assertCreated();

    $order = TicketOrder::query()->where('buyer_email', 'buyer@example.com')->firstOrFail();
    $firstAttendee = $order->attendees()->orderBy('id')->first();

    $value = CustomFieldValue::query()->where('custom_field_id', $field->id)->first();

    expect($value)->not->toBeNull()
        ->and($value->subject_type)->toBe(Attendee::class)
        ->and($value->subject_id)->toBe($firstAttendee->id)
        ->and($value->value)->toBe(['Acme']);
});

it('rejects a checkout that omits a required registration answer', function () {
    $field = CustomField::factory()->ticketRegistration($this->event)->type('text')->required()->create();

    postRegOrder([])
        ->assertStatus(422)
        ->assertJsonValidationErrors("registration.responses.{$field->ulid}");
});

it('drops registration answers keyed by an unknown ulid', function () {
    $field = CustomField::factory()->ticketRegistration($this->event)->type('text')->create();

    postRegOrder([$field->ulid => 'Acme', 'unknown-ulid' => 'ignored'])->assertCreated();

    expect(CustomFieldValue::query()->count())->toBe(1);
});

it('ignores a registration payload when the event configures no fields', function () {
    postRegOrder(['some-ulid' => 'ignored'])->assertCreated();

    expect(CustomFieldValue::query()->count())->toBe(0);
});
