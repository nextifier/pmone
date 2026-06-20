<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id, 'price' => 50000,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
});

function bulkUrl(Event $event): string
{
    return "/api/events/{$event->id}/tickets/bulk-generate";
}

it('lets an authorized user bulk-generate a batch', function () {
    $this->actingAs($this->admin)
        ->postJson(bulkUrl($this->event), [
            'ticket_id' => $this->ticket->id,
            'mode' => 'anonymous',
            'quantity' => 3,
            'delivery' => 'generate_only',
            'batch_label' => 'VIP invites',
        ])
        ->assertStatus(202)
        ->assertJsonPath('data.batch_status', 'processing')
        ->assertJsonPath('data.target', 3);
});

it('forbids a user without the bulk_generate permission', function () {
    $scanner = User::factory()->create(['email_verified_at' => now()]);
    $scanner->assignRole('scanner');

    $this->actingAs($scanner)
        ->postJson(bulkUrl($this->event), [
            'ticket_id' => $this->ticket->id, 'mode' => 'anonymous', 'quantity' => 1, 'delivery' => 'generate_only',
        ])
        ->assertForbidden();
});

it('rejects auto-email when a recipient has no email', function () {
    $this->actingAs($this->admin)
        ->postJson(bulkUrl($this->event), [
            'ticket_id' => $this->ticket->id, 'mode' => 'named',
            'recipients' => [['name' => 'A', 'email' => 'a@x.com'], ['name' => 'B']],
            'delivery' => 'auto_email',
        ])
        ->assertStatus(422);
});

it('rejects named mode without recipients', function () {
    $this->actingAs($this->admin)
        ->postJson(bulkUrl($this->event), [
            'ticket_id' => $this->ticket->id, 'mode' => 'named', 'delivery' => 'generate_only',
        ])
        ->assertStatus(422);
});

it('reports batch progress and exports a CSV', function () {
    Mail::fake();

    $ulid = $this->actingAs($this->admin)
        ->postJson(bulkUrl($this->event), [
            'ticket_id' => $this->ticket->id, 'mode' => 'anonymous', 'quantity' => 4, 'delivery' => 'generate_only',
        ])
        ->json('data.order_ulid');

    // Sync queue ran the job, so the batch is already complete.
    $this->actingAs($this->admin)
        ->getJson("/api/events/{$this->event->id}/tickets/batches/{$ulid}/status")
        ->assertOk()
        ->assertJsonPath('data.batch_status', 'completed')
        ->assertJsonPath('data.generated', 4);

    $response = $this->actingAs($this->admin)
        ->get("/api/events/{$this->event->id}/tickets/batches/{$ulid}/export");

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});
