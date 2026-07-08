<?php

use App\Models\ApiConsumer;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ApiConsumer::factory()->create(['api_key' => 'pk_attendee_reg']);
    $this->headers = ['X-API-Key' => 'pk_attendee_reg'];
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
    $this->ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id,
        'price' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
});

function attendeeRegOrder(array $registrationResponses = []): TicketOrder
{
    $data = [
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => 'buyer@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => test()->ticket->id, 'quantity' => 1]],
    ];

    if ($registrationResponses !== []) {
        $data['registration'] = ['responses' => $registrationResponses];
    }

    return app(TicketPurchaseService::class)->createOrder($data);
}

it('stores provided registration answers without requiring the others', function () {
    $required = CustomField::factory()->ticketRegistration($this->event)->type('text')->required()->create();
    $optional = CustomField::factory()->ticketRegistration($this->event)->type('text')->create();
    $attendee = attendeeRegOrder()->attendees()->orderBy('id')->first();

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", [
            'name' => 'Jane',
            'registration' => [$optional->ulid => 'Acme'],
        ])
        ->assertOk();

    $value = CustomFieldValue::query()->where('custom_field_id', $optional->id)->first();

    expect($value)->not->toBeNull()
        ->and($value->value)->toBe(['Acme'])
        ->and($value->subject_id)->toBe($attendee->id)
        ->and(CustomFieldValue::query()->where('custom_field_id', $required->id)->exists())->toBeFalse();
});

it('rejects an invalid registration answer during personalization', function () {
    $select = CustomField::factory()->ticketRegistration($this->event)->type('select')->create();
    $attendee = attendeeRegOrder()->attendees()->orderBy('id')->first();

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", [
            'name' => 'Jane',
            'registration' => [$select->ulid => 'not-an-option'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors("registration.{$select->ulid}");
});

it('exposes registration fields and answers on the attendee e-ticket', function () {
    $field = CustomField::factory()->ticketRegistration($this->event)->type('text')->create();
    $attendee = attendeeRegOrder([$field->ulid => 'Acme'])->attendees()->orderBy('id')->first();

    $this->withHeaders($this->headers)
        ->getJson("/api/public/attendees/{$attendee->ulid}")
        ->assertOk()
        ->assertJsonCount(1, 'registration_fields')
        ->assertJsonPath('registration_fields.0.ulid', $field->ulid)
        ->assertJsonPath("data.registration_answers.{$field->ulid}", 'Acme');
});

it('exposes per-attendee registration answers on the magic-link order', function () {
    $field = CustomField::factory()->ticketRegistration($this->event)->type('text')->create();
    $order = attendeeRegOrder([$field->ulid => 'Acme']);
    $attendee = $order->attendees()->orderBy('id')->first();
    $token = TicketOrder::magicLinkTokenFor($order->order_number);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/ticket-orders/magic/{$token}")
        ->assertOk()
        ->assertJsonCount(1, 'meta.registration_fields')
        ->assertJsonPath('meta.registration_fields.0.ulid', $field->ulid)
        ->assertJsonPath("meta.registration_answers.{$attendee->ulid}.{$field->ulid}", 'Acme');
});

it('stores registration answers via the admin attendee update', function () {
    $field = CustomField::factory()->ticketRegistration($this->event)->type('text')->create();
    $attendee = attendeeRegOrder()->attendees()->orderBy('id')->first();

    $this->actingAs($this->staff)
        ->patchJson("/api/events/{$this->event->id}/attendees/{$attendee->id}", [
            'registration' => [$field->ulid => 'Acme'],
        ])
        ->assertOk();

    $value = CustomFieldValue::query()->where('custom_field_id', $field->id)->first();

    expect($value)->not->toBeNull()
        ->and($value->value)->toBe(['Acme'])
        ->and($value->subject_id)->toBe($attendee->id);
});
