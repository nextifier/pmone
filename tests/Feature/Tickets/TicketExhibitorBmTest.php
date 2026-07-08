<?php

use App\Models\ApiConsumer;
use App\Models\Attendee;
use App\Models\Brand;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
use App\Models\ExhibitorLead;
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
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true, 'business_matching_enabled' => true]);
});

function confirmedAttendeeFor(Event $event): Attendee
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id, 'tier' => 'VIP']);
    $order = TicketOrder::factory()->confirmed()->create(['event_id' => $event->id]);
    $item = $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);

    return $item->attendees()->create(['ticket_id' => $ticket->id, 'name' => 'Visitor One', 'email' => 'v1@example.com']);
}

it('captures an exhibitor lead and dedupes repeat scans', function () {
    $brand = Brand::factory()->create();
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->brands()->attach($brand->id, ['role' => 'owner']);
    $this->actingAs($exhibitor);

    $attendee = confirmedAttendeeFor($this->event);
    $url = "/api/exhibitor/brands/{$brand->getRouteKey()}/leads/scan";

    $this->postJson($url, ['qr_token' => $attendee->qr_token, 'event_id' => $this->event->id])
        ->assertSuccessful()->assertJsonPath('data.result', 'captured');

    $this->postJson($url, ['qr_token' => $attendee->qr_token, 'event_id' => $this->event->id])
        ->assertSuccessful()->assertJsonPath('data.result', 'already_captured');

    expect(ExhibitorLead::where('brand_id', $brand->id)->count())->toBe(1);
});

it('isolates leads so another brand cannot read them', function () {
    $brandA = Brand::factory()->create();
    $brandB = Brand::factory()->create();
    $userB = User::factory()->create(['email_verified_at' => now()]);
    $userB->brands()->attach($brandB->id, ['role' => 'owner']);
    $this->actingAs($userB);

    // userB belongs to brandB, tries to read brandA leads -> 403.
    $this->getJson("/api/exhibitor/brands/{$brandA->getRouteKey()}/leads")->assertForbidden();
    $this->getJson("/api/exhibitor/brands/{$brandB->getRouteKey()}/leads")->assertSuccessful();
});

it('reports exhibitor lead analytics', function () {
    $brand = Brand::factory()->create();
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->brands()->attach($brand->id, ['role' => 'owner']);
    $this->actingAs($exhibitor);

    $attendee = confirmedAttendeeFor($this->event);
    ExhibitorLead::create([
        'brand_id' => $brand->id, 'attendee_id' => $attendee->id, 'event_id' => $this->event->id, 'scanned_at' => now(),
    ]);

    $this->getJson("/api/exhibitor/brands/{$brand->getRouteKey()}/leads/analytics")
        ->assertSuccessful()->assertJsonPath('data.total', 1);
});

it('resolves a brand_event to its event context for the leads page', function () {
    $brand = Brand::factory()->create();
    $brandEvent = $brand->brandEvents()->create(['event_id' => $this->event->id]);

    // A brand member resolves the numeric event_id from the page URL key.
    $member = User::factory()->create(['email_verified_at' => now()]);
    $member->brands()->attach($brand->id, ['role' => 'owner']);
    $this->actingAs($member);

    $this->getJson("/api/exhibitor/brands/{$brand->getRouteKey()}/leads/context?brand_event_id={$brandEvent->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.event.id', $this->event->id)
        ->assertJsonPath('data.id', $brandEvent->id);

    // Supporting staff (not a brand member) can resolve it too - matching the
    // leads endpoints' staff bypass, so the scanner is never dead-ended.
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('master');
    $this->actingAs($staff);

    $this->getJson("/api/exhibitor/brands/{$brand->getRouteKey()}/leads/context?brand_event_id={$brandEvent->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.event.id', $this->event->id);
});

it('manages business-matching custom fields as admin', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('master');
    $this->actingAs($admin);

    $base = "/api/events/{$this->event->id}/custom-fields";

    $this->postJson($base, ['label' => 'Position', 'type' => 'select', 'options' => ['CEO', 'Manager'], 'required' => true])
        ->assertCreated()->assertJsonPath('data.label', 'Position');

    $field = CustomField::first();
    $this->putJson("{$base}/{$field->id}", ['label' => 'Job Position', 'type' => 'select', 'options' => ['CEO']])
        ->assertSuccessful()->assertJsonPath('data.label', 'Job Position');

    $this->getJson($base)->assertSuccessful()->assertJsonCount(1, 'data');
});

it('lists active custom fields publicly for the checkout form', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_bm']);
    CustomField::factory()->create(['event_id' => $this->event->id, 'label' => 'Interests', 'is_active' => true]);
    CustomField::factory()->create(['event_id' => $this->event->id, 'is_active' => false]);

    $this->withHeaders(['X-API-Key' => 'pk_bm'])
        ->getJson("/api/public/events/{$this->event->slug}/custom-fields")
        ->assertSuccessful()->assertJsonCount(1, 'data');
});

it('stores a translatable custom field label and serves it localized', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('master');
    $this->actingAs($admin);

    $base = "/api/events/{$this->event->id}/custom-fields";
    $this->postJson($base, [
        'label' => ['en' => 'Company size', 'id' => 'Ukuran perusahaan'],
        'type' => 'text',
    ])->assertCreated()
        ->assertJsonPath('data.label', 'Company size')
        ->assertJsonPath('data.label_translations.id', 'Ukuran perusahaan');

    ApiConsumer::factory()->create(['api_key' => 'pk_loc']);
    $this->withHeaders(['X-API-Key' => 'pk_loc'])
        ->getJson("/api/public/events/{$this->event->slug}/custom-fields?locale=id")
        ->assertSuccessful()
        ->assertJsonPath('data.0.label', 'Ukuran perusahaan');
});

it('manages staff terms and serves them localized at the public tickets endpoint', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('master');
    $this->actingAs($admin);

    $this->putJson("/api/events/{$this->event->id}/ticket-settings", [
        'terms' => ['en' => '<p>English terms</p>', 'id' => '<p>Ketentuan Indonesia</p>'],
    ])->assertSuccessful()
        ->assertJsonPath('data.terms.id', '<p>Ketentuan Indonesia</p>');

    ApiConsumer::factory()->create(['api_key' => 'pk_terms']);
    $this->withHeaders(['X-API-Key' => 'pk_terms'])
        ->getJson("/api/public/events/{$this->event->slug}/tickets?locale=id")
        ->assertSuccessful()
        ->assertJsonPath('meta.terms', '<p>Ketentuan Indonesia</p>');

    $this->withHeaders(['X-API-Key' => 'pk_terms'])
        ->getJson("/api/public/events/{$this->event->slug}/tickets?locale=ja")
        ->assertSuccessful()
        ->assertJsonPath('meta.terms', '<p>English terms</p>'); // falls back to en
});

it('stores buyer business-matching answers on order creation', function () {
    $field = CustomField::factory()->create(['event_id' => $this->event->id, 'type' => 'text', 'is_active' => true]);
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->free()->create(['ticket_id' => $ticket->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'bm@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'business_matching' => [
            'opt_in' => true,
            'responses' => [['custom_field_id' => $field->id, 'value' => 'Investor']],
        ],
    ]);

    $buyer = User::where('email', 'bm@example.com')->first();
    expect($buyer->business_matching_opt_in)->toBeTrue();
    expect(CustomFieldValue::where('subject_type', User::class)->where('subject_id', $buyer->id)->where('custom_field_id', $field->id)->exists())->toBeTrue();
});

it('saves and reads business-matching answers from the dashboard', function () {
    $field = CustomField::factory()->create(['event_id' => $this->event->id, 'type' => 'text', 'is_active' => true]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->putJson("/api/my/events/{$this->event->id}/field-responses", [
        'opt_in' => true,
        'responses' => [['custom_field_id' => $field->id, 'value' => 'Tech']],
    ])->assertSuccessful();

    $this->getJson("/api/my/events/{$this->event->id}/field-responses")
        ->assertSuccessful()
        ->assertJsonPath('data.opt_in', true);

    expect(CustomFieldValue::where('subject_type', User::class)->where('subject_id', $user->id)->count())->toBe(1);
});
