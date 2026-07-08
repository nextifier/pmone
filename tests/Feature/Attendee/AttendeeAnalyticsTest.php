<?php

use App\Enums\Ticketing\TicketKind;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Attendee;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'business_matching_enabled' => false,
    ]);
    $this->day = EventDay::factory()->create([
        'event_id' => $this->event->id,
        'day_number' => 1,
        'date' => now()->startOfDay(),
    ]);
    $this->ticket = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'tier' => 'VIP',
        'currency' => 'IDR',
    ]);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

/**
 * Build an order with $qty attendees, the first $checkedIn of which are checked
 * in. Returns the created order.
 */
function makeOrder(array $attrs, int $qty, int $checkedIn = 0): TicketOrder
{
    $order = TicketOrder::factory()->create(array_merge([
        'event_id' => test()->event->id,
    ], $attrs));

    $item = TicketOrderItem::factory()->create([
        'ticket_order_id' => $order->id,
        'ticket_id' => test()->ticket->id,
        'quantity' => $qty,
        'unit_price' => $qty > 0 ? (float) $order->total / $qty : 0,
        'subtotal' => $order->total,
    ]);

    for ($i = 0; $i < $qty; $i++) {
        Attendee::factory()->create([
            'ticket_order_item_id' => $item->id,
            'ticket_id' => test()->ticket->id,
            'checked_in_at' => $i < $checkedIn ? now() : null,
        ]);
    }

    return $order;
}

it('returns summary KPIs for the event', function () {
    makeOrder(['status' => TicketOrderStatus::Confirmed, 'total' => 120000, 'subtotal' => 120000], qty: 3, checkedIn: 2);
    makeOrder(['status' => TicketOrderStatus::Confirmed, 'total' => 0, 'subtotal' => 0], qty: 2);
    makeOrder(['status' => TicketOrderStatus::PendingPayment, 'total' => 60000, 'subtotal' => 60000], qty: 1);

    $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics/summary")
        ->assertOk()
        ->assertJsonPath('data.total_attendees', 6)
        ->assertJsonPath('data.checked_in', 2)
        ->assertJsonPath('data.check_in_rate', 33.3)
        ->assertJsonPath('data.total_orders', 3)
        ->assertJsonPath('data.confirmed_orders', 2)
        ->assertJsonPath('data.pending_orders', 1)
        ->assertJsonPath('data.tickets_sold', 5)
        ->assertJsonPath('data.total_revenue', 120000)
        ->assertJsonPath('data.avg_order_value', 60000)
        ->assertJsonPath('data.currency', 'IDR');
});

it('returns the full detail payload with breakdowns', function () {
    makeOrder(['status' => TicketOrderStatus::Confirmed, 'total' => 120000, 'subtotal' => 120000], qty: 3, checkedIn: 2);
    makeOrder(['status' => TicketOrderStatus::PendingPayment, 'total' => 60000, 'subtotal' => 60000], qty: 1);

    $data = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKeys([
        'summary', 'registrations_over_time', 'check_ins_over_time', 'by_ticket_type',
        'by_event_day', 'by_session', 'payment_channels', 'order_status', 'top_buyers',
        'demographics', 'exhibitor_leads',
    ]);

    expect($data['by_ticket_type'])->toHaveCount(1);
    expect($data['by_ticket_type'][0]['title'])->not->toBe('Unknown ticket');
    expect($data['by_ticket_type'][0]['sold'])->toBe(3);
    expect($data['by_ticket_type'][0]['checked_in'])->toBe(2);

    expect($data['by_event_day'])->toHaveCount(1);
    expect($data['by_event_day'][0]['checked_in'])->toBe(2);

    $statuses = collect($data['order_status'])->keyBy('status');
    expect($statuses['confirmed']['count'])->toBe(1);
    expect($statuses['pending_payment']['count'])->toBe(1);

    expect($data['demographics'])->toBe([]);
    expect($data['exhibitor_leads'])->toBeNull();
});

it('aggregates demographics only when business matching is enabled', function () {
    $this->event->update(['business_matching_enabled' => true]);

    $field = CustomField::create([
        'event_id' => $this->event->id,
        'label' => 'City',
        'type' => 'select',
        'options' => [
            ['value' => 'jakarta', 'label' => 'Jakarta'],
            ['value' => 'bandung', 'label' => 'Bandung'],
        ],
        'required' => false,
        'is_active' => true,
    ]);

    foreach (['jakarta', 'jakarta', 'bandung'] as $city) {
        CustomFieldValue::create([
            'subject_type' => User::class,
            'subject_id' => User::factory()->create()->id,
            'custom_field_id' => $field->id,
            'value' => [$city],
        ]);
    }

    $data = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertOk()
        ->json('data');

    expect($data['demographics'])->toHaveCount(1);
    expect($data['demographics'][0]['label'])->toBe('City');
    expect($data['demographics'][0]['total_responses'])->toBe(3);

    $breakdown = collect($data['demographics'][0]['breakdown'])->keyBy('value');
    expect($breakdown['Jakarta']['count'])->toBe(2);
    expect($breakdown['Bandung']['count'])->toBe(1);

    expect($data['exhibitor_leads'])->not->toBeNull();
    expect($data['exhibitor_leads']['total'])->toBe(0);
});

it('reports business matching opt-in participation', function () {
    $this->event->update(['business_matching_enabled' => true]);

    $field = CustomField::create([
        'event_id' => $this->event->id, 'label' => 'Company', 'type' => 'text', 'required' => false, 'is_active' => true,
    ]);

    $buyerA = User::factory()->create();
    makeOrder(['status' => TicketOrderStatus::Confirmed, 'user_id' => $buyerA->id, 'total' => 0], 1);
    CustomFieldValue::create(['subject_type' => User::class, 'subject_id' => $buyerA->id, 'custom_field_id' => $field->id, 'value' => ['Acme']]);

    // A second confirmed buyer who did not opt in (no answers).
    $buyerB = User::factory()->create();
    makeOrder(['status' => TicketOrderStatus::Confirmed, 'user_id' => $buyerB->id, 'total' => 0], 1);

    $data = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertOk()
        ->json('data');

    expect($data['business_matching']['has_questions'])->toBeTrue()
        ->and($data['business_matching']['buyers'])->toBe(2)
        ->and($data['business_matching']['opted_in'])->toBe(1)
        ->and((float) $data['business_matching']['opt_in_rate'])->toBe(50.0);
});

it('aggregates numeric custom fields with an average and value-ordered breakdown', function () {
    $this->event->update(['business_matching_enabled' => true]);

    $field = CustomField::create([
        'event_id' => $this->event->id, 'label' => 'Satisfaction', 'type' => 'rating', 'required' => false, 'is_active' => true,
    ]);

    foreach ([5, 4, 5] as $score) {
        CustomFieldValue::create([
            'subject_type' => User::class,
            'subject_id' => User::factory()->create()->id,
            'custom_field_id' => $field->id,
            'value' => [$score],
        ]);
    }

    $data = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertOk()
        ->json('data');

    $demo = collect($data['demographics'])->firstWhere('label', 'Satisfaction');
    expect($demo['kind'])->toBe('numeric')
        ->and($demo['total_responses'])->toBe(3)
        ->and($demo['average'])->toBe(4.67);

    $breakdown = collect($demo['breakdown'])->keyBy('value');
    expect($breakdown['4']['count'])->toBe(1)
        ->and($breakdown['5']['count'])->toBe(2);
});

it('exposes ticket capacity and no-show metrics', function () {
    $this->ticket->update(['stock' => 200]);
    // 5 confirmed attendees, 2 checked in -> 3 no-shows (60%).
    makeOrder(['status' => TicketOrderStatus::Confirmed], 5, 2);

    $data = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertOk()
        ->json('data');

    $row = collect($data['by_ticket_type'])->firstWhere('ticket_id', $this->ticket->id);
    expect($row['capacity'])->toBe(200)
        ->and($row['sold'])->toBe(5);

    expect($data['summary']['no_show'])->toBe(3)
        ->and((float) $data['summary']['no_show_rate'])->toBe(60.0);
});

it('reports business-matching respondents and per-field response rate', function () {
    $this->event->update(['business_matching_enabled' => true]);

    $field = CustomField::create([
        'event_id' => $this->event->id, 'label' => 'Industry', 'type' => 'select',
        'options' => ['Tech', 'Finance'], 'required' => false, 'is_active' => true,
    ]);

    $buyer = User::factory()->create();
    makeOrder(['status' => TicketOrderStatus::Confirmed, 'user_id' => $buyer->id, 'total' => 0], 1);
    CustomFieldValue::create(['subject_type' => User::class, 'subject_id' => $buyer->id, 'custom_field_id' => $field->id, 'value' => ['Tech']]);

    $data = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertOk()
        ->json('data');

    expect($data['business_matching']['respondents'])->toBe(1);

    $demo = collect($data['demographics'])->firstWhere('label', 'Industry');
    expect($demo['answered'])->toBe(1)
        ->and((float) $demo['response_rate'])->toBe(100.0);
});

it('labels boolean custom fields as Yes/No', function () {
    $this->event->update(['business_matching_enabled' => true]);

    $field = CustomField::create([
        'event_id' => $this->event->id, 'label' => 'Subscribe', 'type' => 'checkbox', 'required' => false, 'is_active' => true,
    ]);

    foreach ([[true], [true], [false]] as $value) {
        CustomFieldValue::create([
            'subject_type' => User::class,
            'subject_id' => User::factory()->create()->id,
            'custom_field_id' => $field->id,
            'value' => $value,
        ]);
    }

    $data = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertOk()
        ->json('data');

    $demo = collect($data['demographics'])->firstWhere('label', 'Subscribe');
    $breakdown = collect($demo['breakdown'])->keyBy('value');
    expect($breakdown['Yes']['count'])->toBe(2)
        ->and($breakdown['No']['count'])->toBe(1);
});

it('counts per-day check-ins by assigned/valid day and excludes add-ons', function () {
    EventDay::factory()->create([
        'event_id' => $this->event->id,
        'day_number' => 2,
        'date' => now()->startOfDay()->addDay(),
    ]);
    $addon = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'kind' => TicketKind::AddOn,
    ]);

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::Confirmed,
    ]);

    // Entry attendee explicitly assigned to Day 1, checked in.
    $entryItem = TicketOrderItem::factory()->create([
        'ticket_order_id' => $order->id,
        'ticket_id' => $this->ticket->id,
        'selected_event_day_id' => $this->day->id,
        'quantity' => 1,
    ]);
    Attendee::factory()->create([
        'ticket_order_item_id' => $entryItem->id,
        'ticket_id' => $this->ticket->id,
        'checked_in_at' => now(),
    ]);

    // Checked-in add-on attendee must NOT count toward any day.
    $addonItem = TicketOrderItem::factory()->create([
        'ticket_order_id' => $order->id,
        'ticket_id' => $addon->id,
        'quantity' => 1,
    ]);
    Attendee::factory()->create([
        'ticket_order_item_id' => $addonItem->id,
        'ticket_id' => $addon->id,
        'checked_in_at' => now(),
    ]);

    $byDay = collect(
        $this->actingAs($this->staff)
            ->getJson("/api/events/{$this->event->id}/attendees/analytics")
            ->assertOk()
            ->json('data.by_event_day')
    )->keyBy('day_number');

    expect($byDay[1]['checked_in'])->toBe(1);
    expect($byDay[2]['checked_in'])->toBe(0);
});

it('forbids analytics without the attendees.read permission', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('exhibitor');

    $this->actingAs($outsider)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics/summary")
        ->assertForbidden();

    $this->actingAs($outsider)
        ->getJson("/api/events/{$this->event->id}/attendees/analytics")
        ->assertForbidden();
});
