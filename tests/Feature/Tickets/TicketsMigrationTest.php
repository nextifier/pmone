<?php

use App\Models\Attendee;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDay;
use App\Models\ExhibitorLead;
use App\Models\FieldResponse;
use App\Models\Ticket;
use App\Models\TicketOrderItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates all ticketing tables and pivots', function () {
    $tables = [
        'event_days', 'tickets', 'ticket_event_day', 'ticket_price_phases',
        'ticket_sessions', 'ticket_orders', 'ticket_order_items', 'attendees',
        'scan_logs', 'exhibitor_leads', 'event_custom_fields', 'field_responses',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

it('adds ticketing columns to events and users', function () {
    expect(Schema::hasColumns('events', ['timezone', 'allow_cross_day', 'tickets_enabled']))->toBeTrue();
    expect(Schema::hasColumns('users', ['country', 'city', 'profession', 'position', 'business_matching_opt_in']))->toBeTrue();
    expect(Schema::hasColumn('event_conjunctions', 'allow_cross_scan'))->toBeTrue();
});

it('enforces unique event day number per event', function () {
    $event = Event::factory()->create();
    EventDay::factory()->create(['event_id' => $event->id, 'day_number' => 1, 'date' => '2026-04-11']);

    EventDay::factory()->create(['event_id' => $event->id, 'day_number' => 1, 'date' => '2026-04-12']);
})->throws(QueryException::class);

it('enforces unique qr_token on attendees', function () {
    $item = TicketOrderItem::factory()->create();
    $ticket = Ticket::factory()->create();

    Attendee::factory()->create(['ticket_order_item_id' => $item->id, 'ticket_id' => $ticket->id, 'qr_token' => 'DUPLICATE']);
    Attendee::factory()->create(['ticket_order_item_id' => $item->id, 'ticket_id' => $ticket->id, 'qr_token' => 'DUPLICATE']);
})->throws(QueryException::class);

it('enforces unique exhibitor lead per brand and attendee', function () {
    $brand = Brand::factory()->create();
    $attendee = Attendee::factory()->create();
    $event = Event::factory()->create();

    ExhibitorLead::create(['brand_id' => $brand->id, 'attendee_id' => $attendee->id, 'event_id' => $event->id, 'scanned_at' => now()]);
    ExhibitorLead::create(['brand_id' => $brand->id, 'attendee_id' => $attendee->id, 'event_id' => $event->id, 'scanned_at' => now()]);
})->throws(QueryException::class);

it('enforces unique field response per user and custom field', function () {
    $user = User::factory()->create();
    $field = EventCustomField::factory()->create();

    FieldResponse::create(['user_id' => $user->id, 'event_custom_field_id' => $field->id, 'value' => ['a']]);
    FieldResponse::create(['user_id' => $user->id, 'event_custom_field_id' => $field->id, 'value' => ['b']]);
})->throws(QueryException::class);
