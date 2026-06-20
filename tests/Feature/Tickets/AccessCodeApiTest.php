<?php

use App\Models\AccessCode;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_ac']);
    $this->headers = ['X-API-Key' => 'pk_test_ac'];
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
});

function apiTicket(Event $event, float $price = 0, string $visibility = 'public'): Ticket
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id, 'visibility' => $visibility]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

function apiCode(Event $event, Ticket $ticket, array $attributes = []): AccessCode
{
    $code = AccessCode::factory()->create(array_merge([
        'event_id' => $event->id,
        'code' => 'PUBCODE'.fake()->unique()->numerify('####'),
    ], $attributes));
    $code->unlocks()->attach($ticket->id);

    return $code;
}

// ─── Public listing visibility ───────────────────────────────────────────────

it('hides hidden tickets but lists code_required tickets as locked', function () {
    apiTicket($this->event, 50000, 'public');
    apiTicket($this->event, 50000, 'code_required');
    apiTicket($this->event, 50000, 'hidden');

    $res = $this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/tickets")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');

    $visibilities = collect($res->json('data'))->pluck('visibility')->sort()->values()->all();
    expect($visibilities)->toBe(['code_required', 'public']);

    $locked = collect($res->json('data'))->firstWhere('visibility', 'code_required');
    expect($locked['locked'])->toBeTrue();
});

// ─── Public validate endpoint ────────────────────────────────────────────────

it('validates an access code and reveals only the unlocked tickets', function () {
    $hidden = apiTicket($this->event, 0, 'hidden');
    $code = apiCode($this->event, $hidden, ['max_uses' => 10]);

    $res = $this->withHeaders($this->headers)
        ->postJson('/api/public/tickets/validate-access-code', [
            'event_id' => $this->event->id,
            'code' => $code->code,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.unlocks.0.ticket_id', $hidden->id)
        ->assertJsonPath('data.tickets.0.id', $hidden->id)
        ->assertJsonPath('data.tickets.0.visibility', 'hidden');

    // Never leak code internals to the public.
    $data = $res->json('data');
    expect($data)->not->toHaveKeys(['used_count', 'max_uses', 'bind_email', 'bind_phone', 'status']);
});

it('returns 422 for an invalid access code', function () {
    $this->withHeaders($this->headers)
        ->postJson('/api/public/tickets/validate-access-code', [
            'event_id' => $this->event->id,
            'code' => 'WRONG',
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.error_code', 'INVALID_CODE');
});

it('returns 404 validate when tickets are disabled', function () {
    $this->event->update(['tickets_enabled' => false]);

    $this->withHeaders($this->headers)
        ->postJson('/api/public/tickets/validate-access-code', [
            'event_id' => $this->event->id,
            'code' => 'ANY',
        ])
        ->assertNotFound();
});

// ─── Admin CRUD ──────────────────────────────────────────────────────────────

it('lets staff generate a shared access code batch', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    $ticket = apiTicket($this->event, 0, 'code_required');

    $this->postJson("/api/events/{$this->event->id}/access-codes", [
        'name' => 'Press batch',
        'kind' => 'shared',
        'max_uses' => 200,
        'unlocks' => [$ticket->id],
    ])->assertCreated()
        ->assertJsonPath('data.kind', 'shared');

    expect(AccessCode::where('event_id', $this->event->id)->count())->toBe(1);
});

it('lets staff revoke an access code', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    $ticket = apiTicket($this->event, 0, 'code_required');
    $code = apiCode($this->event, $ticket);

    $this->postJson("/api/events/{$this->event->id}/access-codes/{$code->ulid}/revoke")
        ->assertSuccessful();

    expect($code->fresh()->status->value)->toBe('revoked');
});

it('blocks access code management without authentication', function () {
    $this->getJson("/api/events/{$this->event->id}/access-codes")
        ->assertUnauthorized();
});

it('persists ticket visibility set via the admin endpoint', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    $this->postJson("/api/events/{$this->event->id}/tickets", [
        'kind' => 'entry',
        'title' => ['en' => 'VIP Pass'],
        'purchase_type' => 'first_party',
        'visibility' => 'code_required',
    ])->assertCreated()
        ->assertJsonPath('data.visibility', 'code_required');

    expect(Ticket::where('event_id', $this->event->id)->first()->visibility->value)
        ->toBe('code_required');
});
