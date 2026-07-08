<?php

use App\Exports\AttendeesExport;
use App\Exports\ExhibitorLeadsExport;
use App\Models\Attendee;
use App\Models\Brand;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\ExhibitorLead;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'business_matching_enabled' => true,
    ]);
});

it('exports business-matching answers as formatted attendee columns', function () {
    $company = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'text', 'label' => ['en' => 'Company'], 'is_active' => true,
    ]);
    $interests = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'multi_select', 'label' => ['en' => 'Interests'],
        'options' => ['Tech', 'Sales', 'Marketing'], 'is_active' => true,
    ]);
    $subscribe = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'checkbox', 'label' => ['en' => 'Subscribe'], 'is_active' => true,
    ]);

    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    app(TicketPurchaseService::class)->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'business_matching' => ['opt_in' => true, 'responses' => [
            ['custom_field_id' => $company->id, 'value' => 'Acme Corp'],
            ['custom_field_id' => $interests->id, 'value' => ['Tech', 'Marketing']],
            ['custom_field_id' => $subscribe->id, 'value' => true],
        ]],
    ]);

    $export = new AttendeesExport(['event_id' => $this->event->id]);

    expect($export->headings())->toContain('Company', 'Interests', 'Subscribe');

    $attendee = Attendee::query()
        ->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->where('event_id', $this->event->id))
        ->with(['ticket', 'ticketOrderItem.ticketOrder.paymentGateway', 'ticketOrderItem.selectedEventDay', 'ticketOrderItem.ticketSession'])
        ->first();

    $row = $export->map($attendee);

    expect($row)->toContain('Acme Corp', 'Tech, Marketing', 'Yes');
});

it('exports business-matching answers in the exhibitor leads sheet', function () {
    $company = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'text', 'label' => ['en' => 'Company'], 'is_active' => true,
    ]);
    $interests = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'multi_select', 'label' => ['en' => 'Interests'],
        'options' => ['Tech', 'Sales', 'Marketing'], 'is_active' => true,
    ]);

    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    app(TicketPurchaseService::class)->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'lead@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'business_matching' => ['opt_in' => true, 'responses' => [
            ['custom_field_id' => $company->id, 'value' => 'Acme Corp'],
            ['custom_field_id' => $interests->id, 'value' => ['Tech', 'Marketing']],
        ]],
    ]);

    $attendee = Attendee::query()
        ->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->where('event_id', $this->event->id))
        ->first();

    $brand = Brand::factory()->create();
    ExhibitorLead::create([
        'brand_id' => $brand->id, 'attendee_id' => $attendee->id,
        'event_id' => $this->event->id, 'scanned_at' => now(),
    ]);

    $export = new ExhibitorLeadsExport($brand);

    expect($export->headings())->toContain('Company', 'Interests');

    $lead = ExhibitorLead::query()
        ->where('brand_id', $brand->id)
        ->with(['attendee.ticketOrderItem.ticketOrder', 'event'])
        ->first();

    expect($export->map($lead))->toContain('Acme Corp', 'Tech, Marketing');
});

it('shows a dash for attendees whose buyer did not answer', function () {
    CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'text', 'label' => ['en' => 'Company'], 'is_active' => true,
    ]);

    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    app(TicketPurchaseService::class)->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'noanswer@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'business_matching' => ['opt_in' => false, 'responses' => []],
    ]);

    $export = new AttendeesExport(['event_id' => $this->event->id]);
    $attendee = Attendee::query()
        ->whereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->where('event_id', $this->event->id))
        ->with(['ticket', 'ticketOrderItem.ticketOrder.paymentGateway', 'ticketOrderItem.selectedEventDay', 'ticketOrderItem.ticketSession'])
        ->first();

    expect($export->map($attendee))->toContain('-');
});
