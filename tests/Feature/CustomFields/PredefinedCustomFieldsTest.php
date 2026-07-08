<?php

use App\Models\ApiConsumer;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ApiConsumer::factory()->create(['api_key' => 'pk_predefined']);
    $this->headers = ['X-API-Key' => 'pk_predefined'];
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

function togglePredefined(string $systemKey, bool $enabled, string $context = 'ticket_registration')
{
    return test()->actingAs(test()->staff)->putJson(
        '/api/events/'.test()->event->id.'/custom-fields/predefined/'.$systemKey,
        ['context' => $context, 'enabled' => $enabled],
    );
}

it('lists the predefined catalog for both contexts with everything disabled', function () {
    $registration = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/custom-fields/predefined?context=ticket_registration")
        ->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.system_key', 'gender');

    expect(collect($registration->json('data'))->pluck('enabled')->every(fn ($enabled) => $enabled === false))->toBeTrue();

    $businessMatching = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/custom-fields/predefined?context=business_matching")
        ->assertOk()
        ->assertJsonCount(6, 'data');

    expect(collect($businessMatching->json('data'))->pluck('enabled')->every(fn ($enabled) => $enabled === false))->toBeTrue();
});

it('instantiates a predefined field with its catalog labels and options when toggled on', function () {
    togglePredefined('gender', true)
        ->assertOk()
        ->assertJsonPath('data.system_key', 'gender')
        ->assertJsonPath('data.type', 'select');

    $field = CustomField::query()
        ->where('fieldable_id', $this->event->id)
        ->where('context', CustomField::CONTEXT_TICKET_REGISTRATION)
        ->where('system_key', 'gender')
        ->firstOrFail();

    expect($field->is_active)->toBeTrue()
        ->and($field->getTranslations('label'))->toHaveKeys(['en', 'id', 'ja', 'ko', 'zh'])
        ->and($field->options)->toHaveCount(3);
});

it('allows editing an instantiated predefined field label through the normal update endpoint', function () {
    togglePredefined('gender', true)->assertOk();

    $field = CustomField::query()->where('system_key', 'gender')->firstOrFail();

    $this->actingAs($this->staff)
        ->putJson("/api/events/{$this->event->id}/custom-fields/{$field->id}", [
            'context' => 'ticket_registration',
            'label' => ['en' => 'Sex'],
            'type' => 'select',
        ])
        ->assertOk()
        ->assertJsonPath('data.label', 'Sex');

    expect($field->fresh()->getTranslation('label', 'en'))->toBe('Sex');
});

it('only deactivates a predefined field when toggled off, keeping its ulid', function () {
    togglePredefined('gender', true)->assertOk();
    $field = CustomField::query()->where('system_key', 'gender')->firstOrFail();
    $originalUlid = $field->ulid;

    togglePredefined('gender', false)->assertOk();

    $field->refresh();
    expect($field->is_active)->toBeFalse()
        ->and($field->ulid)->toBe($originalUlid);
});

it('reuses the same row when a predefined field is toggled on again', function () {
    togglePredefined('gender', true)->assertOk();
    $field = CustomField::query()->where('system_key', 'gender')->firstOrFail();

    togglePredefined('gender', false)->assertOk();
    togglePredefined('gender', true)->assertOk();

    expect(CustomField::query()->where('system_key', 'gender')->count())->toBe(1)
        ->and(CustomField::query()->where('system_key', 'gender')->first()->id)->toBe($field->id)
        ->and($field->fresh()->is_active)->toBeTrue();
});

it('validates answers for the birth-year years preset at checkout', function () {
    togglePredefined('birth_year', true)->assertOk();

    $field = CustomField::query()->where('system_key', 'birth_year')->firstOrFail();
    expect($field->settings['options_preset'])->toBe('years');

    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $payload = fn (string $year): array => [
        'event_id' => $this->event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => 'buyer@example.com',
        'buyer_phone' => '08123',
        'accept_terms' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'registration' => ['responses' => [$field->ulid => $year]],
    ];

    $this->withHeaders($this->headers)
        ->postJson('/api/public/ticket-orders', $payload('1850'))
        ->assertStatus(422)
        ->assertJsonValidationErrors("registration.responses.{$field->ulid}");

    $this->withHeaders($this->headers)
        ->postJson('/api/public/ticket-orders', $payload((string) now()->year))
        ->assertCreated();
});

it('refuses to delete a predefined field', function () {
    togglePredefined('gender', true)->assertOk();
    $field = CustomField::query()->where('system_key', 'gender')->firstOrFail();

    $this->actingAs($this->staff)
        ->deleteJson("/api/events/{$this->event->id}/custom-fields/{$field->id}")
        ->assertStatus(422);

    $this->assertDatabaseHas('custom_fields', ['id' => $field->id, 'deleted_at' => null]);
});

it('rejects toggling an unknown predefined system key', function () {
    togglePredefined('not-a-real-field', true)->assertStatus(422);
});

it('forbids toggling a predefined field without the update permission', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('exhibitor');

    $this->actingAs($outsider)
        ->putJson("/api/events/{$this->event->id}/custom-fields/predefined/gender", [
            'context' => 'ticket_registration',
            'enabled' => true,
        ])
        ->assertForbidden();
});
