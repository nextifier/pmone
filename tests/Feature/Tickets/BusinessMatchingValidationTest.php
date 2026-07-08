<?php

use App\Models\ApiConsumer;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_bm_validate']);
    $this->headers = ['X-API-Key' => 'pk_bm_validate'];
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'business_matching_enabled' => true,
    ]);
});

function postBmOrder(array $businessMatching): TestResponse
{
    $ticket = onSaleTicket(test()->event, 0);

    return test()->withHeaders(test()->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => 'buyer@example.com',
        'buyer_phone' => '08123',
        'accept_terms' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'business_matching' => $businessMatching,
    ]);
}

it('rejects an order when a required business-matching field is empty', function () {
    $field = CustomField::factory()->create([
        'event_id' => $this->event->id,
        'type' => 'text',
        'required' => true,
        'is_active' => true,
    ]);

    postBmOrder(['opt_in' => true, 'responses' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors("business_matching.responses.{$field->id}");
});

it('rejects a malformed date value', function () {
    $field = CustomField::factory()->create([
        'event_id' => $this->event->id,
        'type' => 'date',
        'required' => false,
        'is_active' => true,
    ]);

    postBmOrder(['opt_in' => true, 'responses' => [
        ['custom_field_id' => $field->id, 'value' => 'not-a-date'],
    ]])
        ->assertStatus(422)
        ->assertJsonValidationErrors("business_matching.responses.{$field->id}");
});

it('rejects a select value outside the configured options', function () {
    $field = CustomField::factory()->create([
        'event_id' => $this->event->id,
        'type' => 'select',
        'options' => ['Tech', 'Sales'],
        'required' => false,
        'is_active' => true,
    ]);

    postBmOrder(['opt_in' => true, 'responses' => [
        ['custom_field_id' => $field->id, 'value' => 'Marketing'],
    ]])
        ->assertStatus(422)
        ->assertJsonValidationErrors("business_matching.responses.{$field->id}");
});

it('does not enforce required fields when the buyer opted out', function () {
    CustomField::factory()->create([
        'event_id' => $this->event->id,
        'type' => 'text',
        'required' => true,
        'is_active' => true,
    ]);

    postBmOrder(['opt_in' => false, 'responses' => []])->assertCreated();

    expect(CustomFieldValue::count())->toBe(0);
});

it('accepts and stores valid typed business-matching answers', function () {
    $text = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'text', 'required' => true, 'is_active' => true,
    ]);
    $date = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'date', 'required' => false, 'is_active' => true,
    ]);
    $select = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'select', 'options' => ['Tech', 'Sales'], 'is_active' => true,
    ]);

    postBmOrder(['opt_in' => true, 'responses' => [
        ['custom_field_id' => $text->id, 'value' => 'Acme'],
        ['custom_field_id' => $date->id, 'value' => '2026-02-03'],
        ['custom_field_id' => $select->id, 'value' => 'Tech'],
    ]])->assertCreated();

    $buyer = User::where('email', 'buyer@example.com')->first();
    expect($buyer->business_matching_opt_in)->toBeTrue()
        ->and(CustomFieldValue::where('subject_type', User::class)->where('subject_id', $buyer->id)->count())->toBe(3)
        ->and(CustomFieldValue::where('custom_field_id', $date->id)->first()->value)->toBe(['2026-02-03']);
});

it('enforces required business-matching fields on the attendee dashboard', function () {
    $field = CustomField::factory()->create([
        'event_id' => $this->event->id, 'type' => 'text', 'required' => true, 'is_active' => true,
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->putJson("/api/my/events/{$this->event->id}/field-responses", [
            'opt_in' => true,
            'responses' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors("responses.{$field->id}");
});
