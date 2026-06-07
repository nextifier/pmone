<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    $this->apiConsumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_faqs',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create(['email' => 'hello@expo.test']);
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-06-04 10:00:00',
        'end_date' => '2026-06-07 19:00:00',
        'location' => 'NICE, PIK 2',
        'location_link' => 'https://maps.example/abc',
        'hall' => 'Hall A',
    ]);

    $this->endpoint = "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/faqs";
});

test('returns 401 without api key', function () {
    $this->getJson($this->endpoint)->assertUnauthorized();
});

test('resolves event context tokens in the answer', function () {
    Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Where?', 'id' => 'Di mana?'],
        'answer' => ['en' => '<p>At {{event_location}} ({{event_hall}}) on {{event_date}}.</p>', 'id' => '<p>x</p>'],
        'order_column' => 1,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=en');

    $response->assertOk()
        ->assertJsonPath('data.0.q', 'Where?')
        ->assertJsonPath('data.0.a', '<p>At NICE, PIK 2 (Hall A) on '.$this->event->date_label.'.</p>');
});

test('resolves contact email and location link tokens', function () {
    Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Contact?', 'id' => 'Kontak?'],
        'answer' => ['en' => '<a href="{{location_link}}">map</a> {{contact_email}}', 'id' => 'x'],
        'order_column' => 1,
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertOk()
        ->assertJsonPath('data.0.a', '<a href="https://maps.example/abc">map</a> hello@expo.test');
});

test('answer changes when the event date changes (dynamic, single source of truth)', function () {
    Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'When?', 'id' => 'Kapan?'],
        'answer' => ['en' => '<p>{{event_date}}</p>', 'id' => 'x'],
        'order_column' => 1,
    ]);

    $first = $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=en')->json('data.0.a');

    $this->event->update(['start_date' => '2027-01-10 09:00:00', 'end_date' => '2027-01-12 18:00:00']);
    ResponseCache::clear();

    $second = $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=en')->json('data.0.a');

    expect($second)->not->toBe($first);
    expect($second)->toBe('<p>'.$this->event->fresh()->date_label.'</p>');
});

test('returns indonesian translation when locale=id', function () {
    Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Q', 'id' => 'Pertanyaan'],
        'answer' => ['en' => 'A', 'id' => 'Jawaban'],
        'order_column' => 1,
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=id')
        ->assertOk()
        ->assertJsonPath('data.0.q', 'Pertanyaan')
        ->assertJsonPath('data.0.a', 'Jawaban');
});

test('hides inactive faqs and orders by order_column', function () {
    $second = Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Second', 'id' => 'Kedua'],
        'answer' => ['en' => 'b', 'id' => 'b'],
    ]);
    $first = Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'First', 'id' => 'Pertama'],
        'answer' => ['en' => 'a', 'id' => 'a'],
    ]);
    Faq::factory()->inactive()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Hidden', 'id' => 'Tersembunyi'],
        'answer' => ['en' => 'h', 'id' => 'h'],
    ]);

    $first->order_column = 1;
    $first->save();
    $second->order_column = 2;
    $second->save();

    $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.q', 'First')
        ->assertJsonPath('data.1.q', 'Second');
});

test('falls back to a previous event faq, resolving tokens from the current event', function () {
    // current event ($this->event) has no FAQ; an older event does
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2024-01-01 10:00:00',
        'location' => 'Old Venue',
    ]);
    Faq::factory()->create([
        'event_id' => $older->id,
        'question' => ['en' => 'About {{event_title}}', 'id' => 'x'],
        'answer' => ['en' => '<p>At {{event_location}} on {{event_date}}</p>', 'id' => 'x'],
        'order_column' => 1,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=en');

    $response->assertOk()->assertJsonCount(1, 'data');
    // Tokens resolve from the CURRENT event, not the older source event.
    expect($response->json('data.0.a'))->toContain('NICE, PIK 2');
    expect($response->json('data.0.a'))->toContain($this->event->date_label);
    expect($response->json('data.0.a'))->not->toContain('Old Venue');
});

test('does not fall back when the event has its own faq', function () {
    Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Own FAQ', 'id' => 'x'],
        'answer' => ['en' => 'a', 'id' => 'a'],
        'order_column' => 1,
    ]);
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2024-01-01 10:00:00',
    ]);
    Faq::factory()->create(['event_id' => $older->id, 'order_column' => 1]);

    $this->withHeaders(['X-API-Key' => 'pk_test_faqs'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.q', 'Own FAQ');
});
