<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_brand_cf', 'is_active' => true]);
    $this->headers = ['X-API-Key' => 'pk_test_brand_cf'];

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);
});

it('exposes every filled brand custom field with label and formatted value', function () {
    // A whitelisted-style select and a previously-hidden field.
    $concept = CustomField::factory()->brand($this->project)
        ->type(CustomField::TYPE_SELECT)
        ->create([
            'label' => ['en' => 'Business Concept'],
            'key' => 'business_concept',
            'order_column' => 1,
            'options' => [
                ['value' => 'Franchise', 'label' => 'Franchise'],
                ['value' => 'Retail', 'label' => 'Retail'],
            ],
        ]);

    $buyerTarget = CustomField::factory()->brand($this->project)
        ->type(CustomField::TYPE_TEXT)
        ->create([
            'label' => ['en' => 'Buyer Target #1'],
            'key' => 'buyer_target_1',
            'order_column' => 2,
        ]);

    $brand = Brand::factory()->create([
        'status' => 'active',
        'visibility' => 'public',
        'custom_fields' => [
            'business_concept' => 'Retail',
            'buyer_target_1' => 'SMEs',
        ],
    ]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands/{$brand->slug}")
        ->assertOk();

    $fields = collect($response->json('data.custom_fields'));

    // Array shape of {key, label, value}, in definition order.
    expect($fields)->toHaveCount(2);
    expect($fields[0]['label'])->toBe('Business Concept');
    expect($fields[0]['value'])->toBe('Retail'); // option label, not raw value
    // The previously non-whitelisted field is now public too.
    expect($fields[1]['label'])->toBe('Buyer Target #1');
    expect($fields[1]['value'])->toBe('SMEs');
});

it('hides custom fields marked as not public', function () {
    CustomField::factory()->brand($this->project)
        ->type(CustomField::TYPE_TEXT)
        ->create([
            'label' => ['en' => 'Public Tagline'],
            'key' => 'public_tagline',
            'order_column' => 1,
        ]);

    CustomField::factory()->brand($this->project)
        ->type(CustomField::TYPE_TEXT)
        ->create([
            'label' => ['en' => 'Buyer Target #1'],
            'key' => 'buyer_target_1',
            'order_column' => 2,
            'settings' => ['public' => false],
        ]);

    $brand = Brand::factory()->create([
        'status' => 'active',
        'visibility' => 'public',
        'custom_fields' => [
            'public_tagline' => 'We sell things',
            'buyer_target_1' => 'SMEs',
        ],
    ]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    $fields = collect(
        $this->withHeaders($this->headers)
            ->getJson("/api/public/projects/{$this->project->username}/brands/{$brand->slug}")
            ->assertOk()
            ->json('data.custom_fields')
    );

    expect($fields)->toHaveCount(1);
    expect($fields->pluck('label')->all())->toBe(['Public Tagline']);
    expect($fields->pluck('key')->all())->not->toContain('buyer_target_1');
});

it('drops empty custom fields', function () {
    CustomField::factory()->brand($this->project)
        ->type(CustomField::TYPE_TEXT)
        ->create(['label' => ['en' => 'Tagline'], 'key' => 'tagline', 'order_column' => 1]);

    $brand = Brand::factory()->create([
        'status' => 'active',
        'visibility' => 'public',
        'custom_fields' => ['tagline' => ''],
    ]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands/{$brand->slug}")
        ->assertOk()
        ->assertJsonCount(0, 'data.custom_fields');
});
