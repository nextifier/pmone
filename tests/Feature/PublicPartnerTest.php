<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Partner;
use App\Models\PartnerCategory;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_partner', 'is_active' => true]);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-06-04 10:00:00',
    ]);

    $this->endpoint = "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/partners";

    $this->attachPartner = function (PartnerCategory $category, Partner $partner, int $order): void {
        $category->partners()->attach($partner->id, ['order_column' => $order]);
    };

    $this->partnerWithLogo = function (string $name, string $file = 'logo.png'): Partner {
        $partner = Partner::factory()->create(['name' => $name, 'website_url' => "https://{$name}.test"]);
        $partner->addMedia(UploadedFile::fake()->image($file, 480, 240))->toMediaCollection('partner_logo');

        return $partner;
    };
});

test('returns 401 without api key', function () {
    $this->getJson($this->endpoint)->assertUnauthorized();
});

test('returns categories with partners in the expected shape', function () {
    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Gold Partners',
        'no_container' => true,
    ]);
    ($this->attachPartner)($category, ($this->partnerWithLogo)('acme'), 1);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_partner'])->getJson($this->endpoint);

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0'))->toHaveKeys(['category', 'no_container', 'partners']);
    expect($response->json('data.0.category'))->toBe('Gold Partners');
    expect($response->json('data.0.no_container'))->toBeTrue();
    expect($response->json('data.0.partners.0'))->toHaveKeys(['name', 'logo', 'link']);
    expect($response->json('data.0.partners.0.name'))->toBe('acme');
    expect($response->json('data.0.partners.0.link'))->toBe('https://acme.test');
});

test('exposes a webp sm logo conversion for raster logos', function () {
    $category = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Sponsors']);
    ($this->attachPartner)($category, ($this->partnerWithLogo)('acme'), 1);

    $logo = $this->withHeaders(['X-API-Key' => 'pk_test_partner'])
        ->getJson($this->endpoint)
        ->json('data.0.partners.0.logo');

    expect($logo['sm'])->not->toBeNull()->toEndWith('.webp');
    expect($logo['url'])->toEndWith('.png');
});

test('falls back to the original logo url for svg (no raster conversion)', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="120"><rect width="240" height="120"/></svg>';
    $partner = Partner::factory()->create(['name' => 'vector']);
    $partner->addMedia(UploadedFile::fake()->createWithContent('logo.svg', $svg))
        ->toMediaCollection('partner_logo');

    $category = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Organized by']);
    ($this->attachPartner)($category, $partner, 1);

    $logo = $this->withHeaders(['X-API-Key' => 'pk_test_partner'])
        ->getJson($this->endpoint)
        ->json('data.0.partners.0.logo');

    expect($logo['url'])->toEndWith('.svg');
    expect($logo['sm'])->toBe($logo['url']);
});

test('only includes active partners', function () {
    $category = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Media Partners']);
    ($this->attachPartner)($category, ($this->partnerWithLogo)('active-one'), 1);
    ($this->attachPartner)($category, Partner::factory()->inactive()->create(['name' => 'hidden']), 2);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_partner'])->getJson($this->endpoint);

    $response->assertOk()->assertJsonCount(1, 'data.0.partners');
    expect($response->json('data.0.partners.0.name'))->toBe('active-one');
});

test('orders categories and partners by order_column', function () {
    $first = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'First']);
    $first->update(['order_column' => 1]);
    $second = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Second']);
    $second->update(['order_column' => 2]);

    ($this->attachPartner)($second, ($this->partnerWithLogo)('beta'), 1);
    ($this->attachPartner)($first, ($this->partnerWithLogo)('alpha-2'), 2);
    ($this->attachPartner)($first, ($this->partnerWithLogo)('alpha-1'), 1);

    $data = $this->withHeaders(['X-API-Key' => 'pk_test_partner'])
        ->getJson($this->endpoint)
        ->json('data');

    expect(array_column($data, 'category'))->toBe(['First', 'Second']);
    expect(array_column($data[0]['partners'], 'name'))->toBe(['alpha-1', 'alpha-2']);
});

test('omits categories that have no active partners', function () {
    PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Empty']);
    $withPartner = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Filled']);
    ($this->attachPartner)($withPartner, ($this->partnerWithLogo)('acme'), 1);

    $this->withHeaders(['X-API-Key' => 'pk_test_partner'])
        ->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
