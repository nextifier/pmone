<?php

use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\TicketDocumentService;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->day = EventDay::factory()->create([
        'event_id' => $this->event->id, 'day_number' => 1, 'date' => now()->startOfDay()->addDay(),
    ]);
    $this->ticket = Ticket::factory()->create([
        'event_id' => $this->event->id, 'requires_day_selection' => true, 'max_quantity' => null,
    ]);
    $this->ticket->validDays()->sync([$this->day->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

function attendeesForEvent(int $qty = 1)
{
    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => test()->ticket->id, 'quantity' => $qty, 'selected_event_day_id' => test()->day->id]],
    ]);

    return $order->attendees()->get();
}

it('lets staff bulk soft-delete attendees', function () {
    $ids = attendeesForEvent(3)->pluck('id')->all();

    $this->actingAs($this->staff)
        ->deleteJson("/api/events/{$this->event->id}/attendees/bulk", ['ids' => $ids])
        ->assertOk()
        ->assertJsonPath('deleted_count', 3);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('attendees', ['id' => $id]);
    }
});

it('soft-deletes a single attendee by id without touching the order', function () {
    $attendee = attendeesForEvent(1)->first();
    $itemId = $attendee->ticket_order_item_id;

    $this->actingAs($this->staff)
        ->deleteJson("/api/events/{$this->event->id}/attendees/{$attendee->id}")
        ->assertOk();

    $this->assertSoftDeleted('attendees', ['id' => $attendee->id]);
    $this->assertDatabaseHas('ticket_order_items', ['id' => $itemId, 'deleted_at' => null]);
});

it('forbids delete without the attendees.delete permission', function () {
    $attendee = attendeesForEvent(1)->first();
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('exhibitor');

    $this->actingAs($outsider)
        ->deleteJson("/api/events/{$this->event->id}/attendees/{$attendee->id}")
        ->assertForbidden();
});

it('does not bulk-delete attendees that belong to another event', function () {
    $attendee = attendeesForEvent(1)->first();
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);

    $this->actingAs($this->staff)
        ->deleteJson("/api/events/{$otherEvent->id}/attendees/bulk", ['ids' => [$attendee->id]])
        ->assertOk()
        ->assertJsonPath('deleted_count', 0);

    $this->assertDatabaseHas('attendees', ['id' => $attendee->id, 'deleted_at' => null]);
});

it('lists, restores and permanently deletes trashed attendees', function () {
    $attendee = attendeesForEvent(1)->first();
    $attendee->delete();

    $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/trash")
        ->assertOk()
        ->assertJsonPath('data.0.id', $attendee->id);

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/attendees/trash/{$attendee->id}/restore")
        ->assertOk();

    expect($attendee->fresh()->trashed())->toBeFalse();

    $attendee->delete();
    $this->actingAs($this->staff)
        ->deleteJson("/api/events/{$this->event->id}/attendees/trash/{$attendee->id}")
        ->assertOk();

    $this->assertDatabaseMissing('attendees', ['id' => $attendee->id]);
});

it('exposes order payment fields in the index and exports xlsx', function () {
    $gateway = ProjectPaymentGateway::factory()->create([
        'project_id' => $this->project->id, 'mode' => 'test', 'provider' => 'xendit',
    ]);
    $attendee = attendeesForEvent(1)->first();
    $attendee->ticketOrderItem->ticketOrder->update([
        'payment_gateway_id' => $gateway->id, 'payment_channel' => 'BCAVA', 'paid_at' => now(),
        'status' => 'confirmed', 'subtotal' => 100000, 'total' => 100000,
    ]);

    $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees")
        ->assertOk()
        ->assertJsonPath('data.0.payment_channel', 'BCAVA')
        ->assertJsonPath('data.0.payment_mode', 'test')
        ->assertJsonPath('data.0.order.status', 'confirmed')
        ->assertJsonPath('data.0.can_delete', true)
        ->assertJsonPath('data.0.can_view_documents', true);

    $this->actingAs($this->staff)
        ->get("/api/events/{$this->event->id}/attendees/export")
        ->assertOk();
});

it('forbids export without the attendees.export permission', function () {
    attendeesForEvent(1);
    $scanner = User::factory()->create(['email_verified_at' => now()]);
    $scanner->assignRole('scanner');

    $this->actingAs($scanner)
        ->get("/api/events/{$this->event->id}/attendees/export")
        ->assertForbidden();
});

it('streams the per-order invoice and receipt pdf for a paid order', function () {
    $attendee = attendeesForEvent(1)->first();
    $order = $attendee->ticketOrderItem->ticketOrder;
    $order->update(['payment_channel' => 'BCAVA', 'status' => 'confirmed', 'subtotal' => 100000, 'total' => 100000, 'paid_at' => now()]);

    $this->mock(TicketDocumentService::class, function ($mock) {
        $mock->shouldReceive('renderInvoicePdf')->once()->andReturn(response('INVOICE', 200));
        $mock->shouldReceive('renderReceiptPdf')->once()->andReturn(response('RECEIPT', 200));
    });

    $this->actingAs($this->staff)
        ->get("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/invoice.pdf")
        ->assertOk()
        ->assertSee('INVOICE');

    $this->actingAs($this->staff)
        ->get("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/receipt.pdf")
        ->assertOk()
        ->assertSee('RECEIPT');
});

it('returns 422 for the receipt pdf when the order is unpaid', function () {
    $attendee = attendeesForEvent(1)->first();
    $order = $attendee->ticketOrderItem->ticketOrder;
    $order->update(['paid_at' => null, 'status' => 'pending_payment']);

    $this->actingAs($this->staff)
        ->get("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/receipt.pdf")
        ->assertStatus(422);
});
