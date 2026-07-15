<?php

use App\Enums\BoothType;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    $permissions = ['brands.create', 'brands.read', 'brands.update', 'brands.delete'];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $exhibitorRole = Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);
    $exhibitorRole->syncPermissions(['brands.read', 'brands.update']);

    $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $staffRole->syncPermissions(['brands.create', 'brands.read', 'brands.update', 'brands.delete']);

    // Create master user
    $this->user = User::factory()->create();
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    // Create project and event
    $this->project = Project::factory()->create([
        'status' => 'active',
        'username' => 'test-project',
    ]);

    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'test-event',
    ]);

    $this->baseUrl = "/api/projects/{$this->project->username}/events/{$this->event->slug}/brands";
});

// ============================================================
// Brand CRUD
// ============================================================

test('staff can list brands in event', function () {
    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->getJson($this->baseUrl);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBe(1);
});

test('staff can add new brand to event', function () {
    $response = $this->postJson($this->baseUrl, [
        'brand_name' => 'New Test Brand',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data',
        ]);

    $this->assertDatabaseHas('brands', [
        'name' => 'New Test Brand',
    ]);

    $brand = Brand::where('name', 'New Test Brand')->first();
    $this->assertDatabaseHas('brand_event', [
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);
});

test('staff can add new brand to event with country', function () {
    $response = $this->postJson($this->baseUrl, [
        'brand_name' => 'Brand With Country',
        'country' => 'Indonesia',
    ]);

    $response->assertStatus(201);

    $brand = Brand::where('name', 'Brand With Country')->first();
    expect($brand->address['country'] ?? null)->toBe('Indonesia');
});

test('attaching an existing brand fills country when the brand has none', function () {
    $brand = Brand::factory()->create(['name' => 'Countryless Brand', 'address' => null]);

    $secondEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'country-event',
    ]);
    $secondUrl = "/api/projects/{$this->project->username}/events/{$secondEvent->slug}/brands";

    $this->postJson($secondUrl, [
        'brand_name' => 'Countryless Brand',
        'country' => 'Singapore',
    ])->assertStatus(201);

    expect($brand->fresh()->address['country'] ?? null)->toBe('Singapore');
});

test('staff can add new brand to event with notes', function () {
    $response = $this->postJson($this->baseUrl, [
        'brand_name' => 'Brand With Notes',
        'notes' => 'Paid deposit, pending contract.',
    ]);

    $response->assertStatus(201);

    $brand = Brand::where('name', 'Brand With Notes')->first();
    $this->assertDatabaseHas('brand_event', [
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'notes' => 'Paid deposit, pending contract.',
    ]);
});

test('staff can add brand with any booth type', function (string $boothType) {
    $response = $this->postJson($this->baseUrl, [
        'brand_name' => "Brand {$boothType}",
        'booth_type' => $boothType,
    ]);

    $response->assertStatus(201);

    $brand = Brand::where('name', "Brand {$boothType}")->first();
    $this->assertDatabaseHas('brand_event', [
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'booth_type' => $boothType,
    ]);
})->with(array_column(BoothType::cases(), 'value'));

test('adding a brand with an unknown booth type fails validation', function () {
    $this->postJson($this->baseUrl, [
        'brand_name' => 'Bad Booth Brand',
        'booth_type' => 'rooftop_garden',
    ])->assertJsonValidationErrors(['booth_type']);
});

test('staff can add existing brand to event', function () {
    $brand = Brand::factory()->create(['name' => 'Existing Brand']);

    // Create a second event to attach the existing brand to
    $secondEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'second-event',
    ]);
    $secondUrl = "/api/projects/{$this->project->username}/events/{$secondEvent->slug}/brands";

    $response = $this->postJson($secondUrl, [
        'brand_name' => 'Existing Brand',
    ]);

    $response->assertStatus(201);

    // Should not create a duplicate brand
    expect(Brand::where('name', 'Existing Brand')->count())->toBe(1);

    // Should create a brand_event for the second event
    $this->assertDatabaseHas('brand_event', [
        'brand_id' => $brand->id,
        'event_id' => $secondEvent->id,
    ]);
});

test('adding duplicate brand to same event returns 409', function () {
    $brand = Brand::factory()->create(['name' => 'Duplicate Brand']);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->postJson($this->baseUrl, [
        'brand_name' => 'Duplicate Brand',
    ]);

    $response->assertStatus(409);
});

test('brand name is required when creating', function () {
    $response = $this->postJson($this->baseUrl, [
        'brand_name' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['brand_name']);
});

test('staff can view brand-event detail', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->getJson("{$this->baseUrl}/{$brand->slug}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
        ]);
});

test('staff can update booth info', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->putJson("{$this->baseUrl}/{$brand->slug}", [
        'booth_number' => 'A-101',
        'booth_size' => 36.5,
        'booth_type' => 'raw_space',
        'status' => 'confirmed',
    ]);

    $response->assertSuccessful();

    $brandEvent->refresh();
    expect($brandEvent->booth_number)->toBe('A-101');
    expect((float) $brandEvent->booth_size)->toBe(36.5);
    expect($brandEvent->status)->toBe('confirmed');
});

test('staff can set fascia name longer than 24 characters', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $longFascia = str_repeat('A', 30);

    $response = $this->putJson("{$this->baseUrl}/{$brand->slug}", [
        'fascia_name' => $longFascia,
    ]);

    $response->assertSuccessful();
    expect($brandEvent->refresh()->fascia_name)->toBe($longFascia);
});

test('exhibitor cannot set fascia name longer than 24 characters', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create();
    $brand->users()->attach($exhibitor->id);
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($exhibitor);

    $response = $this->putJson(
        "/api/exhibitor/brands/{$brand->slug}/events/{$brandEvent->id}/booth-fields",
        ['fascia_name' => str_repeat('A', 30)]
    );

    $response->assertStatus(422);
});

test('project resolves whatsapp contact number by label priority', function () {
    $withPc = Project::factory()->create([
        'phone' => [
            ['label' => 'WhatsApp Sales', 'number' => '0812 3456 7890'],
            ['label' => 'WhatsApp PC', 'number' => '+62 856-1111-2222'],
        ],
    ]);
    expect($withPc->whatsappContactNumber())->toBe('6285611112222');

    $salesOnly = Project::factory()->create([
        'phone' => [['label' => 'WhatsApp Sales', 'number' => '08123456789']],
    ]);
    expect($salesOnly->whatsappContactNumber())->toBe('628123456789');

    $neither = Project::factory()->create([
        'phone' => [['label' => 'WhatsApp Marketing', 'number' => '08110001111']],
    ]);
    expect($neither->whatsappContactNumber())->toBeNull();
});

test('staff can update brand profile', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->putJson("{$this->baseUrl}/{$brand->slug}/profile", [
        'name' => 'Updated Brand Name',
        'company_name' => 'Updated Company',
        'description' => '<p>Updated description</p>',
    ]);

    $response->assertSuccessful();

    $brand->refresh();
    expect($brand->name)->toBe('Updated Brand Name');
    expect($brand->company_name)->toBe('Updated Company');
});

test('staff can remove brand from event', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->deleteJson("{$this->baseUrl}/{$brand->slug}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('brand_event', [
        'id' => $brandEvent->id,
    ]);

    // Brand itself should still exist
    $this->assertDatabaseHas('brands', [
        'id' => $brand->id,
    ]);
});

test('listing brands returns paginated results', function () {
    $brands = Brand::factory()->count(5)->create();
    foreach ($brands as $brand) {
        BrandEvent::factory()->create([
            'brand_id' => $brand->id,
            'event_id' => $this->event->id,
        ]);
    }

    $response = $this->getJson("{$this->baseUrl}?per_page=2");

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('meta.per_page'))->toBe(2);
    expect(count($response->json('data')))->toBe(2);
});

test('store brand with emails attaches users', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson($this->baseUrl, [
        'brand_name' => 'Brand With Members',
        'emails' => ['existing@example.com', 'newuser@example.com'],
    ]);

    $response->assertStatus(201);

    $brand = Brand::where('name', 'Brand With Members')->first();

    // Existing user should be attached
    expect($brand->users()->where('user_id', $existingUser->id)->exists())->toBeTrue();

    // New user should be created and attached
    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($brand->users()->where('user_id', $newUser->id)->exists())->toBeTrue();

    // New user should have exhibitor role
    expect($newUser->hasRole('exhibitor'))->toBeTrue();
});

// ============================================================
// Member Management
// ============================================================

test('staff can list brand members', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $member = User::factory()->create();
    $brand->users()->attach($member->id);

    $response = $this->getJson("{$this->baseUrl}/{$brand->slug}/members");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('staff can add member by email', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $member = User::factory()->create(['email' => 'member@example.com']);

    $response = $this->postJson("{$this->baseUrl}/{$brand->slug}/members", [
        'email' => 'member@example.com',
    ]);

    $response->assertStatus(201);

    expect($brand->users()->where('user_id', $member->id)->exists())->toBeTrue();
    expect($member->fresh()->hasRole('exhibitor'))->toBeTrue();
});

test('adding member creates new user if not exists', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->postJson("{$this->baseUrl}/{$brand->slug}/members", [
        'email' => 'brand-new-user@example.com',
    ]);

    $response->assertStatus(201);

    $newUser = User::where('email', 'brand-new-user@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($brand->users()->where('user_id', $newUser->id)->exists())->toBeTrue();
    expect($newUser->hasRole('exhibitor'))->toBeTrue();
});

test('adding existing member to brand is idempotent', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $member = User::factory()->create(['email' => 'member@example.com']);
    $brand->users()->attach($member->id);

    $response = $this->postJson("{$this->baseUrl}/{$brand->slug}/members", [
        'email' => 'member@example.com',
    ]);

    $response->assertStatus(201);

    // Should still only have one entry
    expect($brand->users()->where('user_id', $member->id)->count())->toBe(1);
});

test('staff can remove member from brand', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $member = User::factory()->create();
    $brand->users()->attach($member->id);

    $response = $this->deleteJson("{$this->baseUrl}/{$brand->slug}/members/{$member->id}");

    $response->assertSuccessful();

    expect($brand->users()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('member email is required when adding', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->postJson("{$this->baseUrl}/{$brand->slug}/members", [
        'email' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('member email must be valid', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->postJson("{$this->baseUrl}/{$brand->slug}/members", [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// ============================================================
// Promotion Posts
// ============================================================

test('staff can list promotion posts', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    PromotionPost::factory()->count(3)->create([
        'brand_event_id' => $brandEvent->id,
    ]);

    $response = $this->getJson("{$this->baseUrl}/{$brand->slug}/promotion-posts");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('staff can create promotion post', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->postJson("{$this->baseUrl}/{$brand->slug}/promotion-posts", [
        'caption' => 'Visit our booth for exclusive deals!',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('promotion_posts', [
        'brand_event_id' => $brandEvent->id,
        'caption' => 'Visit our booth for exclusive deals!',
    ]);
});

test('staff can update promotion post', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $post = PromotionPost::factory()->create([
        'brand_event_id' => $brandEvent->id,
        'caption' => 'Original caption',
    ]);

    $response = $this->putJson("{$this->baseUrl}/{$brand->slug}/promotion-posts/{$post->id}", [
        'caption' => 'Updated caption',
    ]);

    $response->assertSuccessful();

    $post->refresh();
    expect($post->caption)->toBe('Updated caption');
});

test('staff can delete promotion post', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $post = PromotionPost::factory()->create([
        'brand_event_id' => $brandEvent->id,
    ]);

    $response = $this->deleteJson("{$this->baseUrl}/{$brand->slug}/promotion-posts/{$post->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('promotion_posts', [
        'id' => $post->id,
    ]);
});

test('promotion post with null caption is valid', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->postJson("{$this->baseUrl}/{$brand->slug}/promotion-posts", [
        'caption' => null,
    ]);

    $response->assertStatus(201);
});

// ============================================================
// Authorization (Policy)
// ============================================================

test('unauthenticated user cannot access brands', function () {
    // Reset authentication
    $this->app['auth']->forgetGuards();

    $response = $this->getJson($this->baseUrl);

    $response->assertUnauthorized();
});

test('exhibitor can update brand profile on their brand', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    // Attach exhibitor to the brand
    $brand->users()->attach($exhibitor->id);

    $this->actingAs($exhibitor);

    $response = $this->putJson("{$this->baseUrl}/{$brand->slug}/profile", [
        'company_name' => 'Exhibitor Updated Company',
    ]);

    $response->assertSuccessful();

    $brand->refresh();
    expect($brand->company_name)->toBe('Exhibitor Updated Company');
});

test('exhibitor cannot update booth info', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    $brand->users()->attach($exhibitor->id);

    $this->actingAs($exhibitor);

    $response = $this->putJson("{$this->baseUrl}/{$brand->slug}", [
        'booth_number' => 'X-999',
        'status' => 'confirmed',
    ]);

    $response->assertForbidden();
});

test('exhibitor cannot update profile on brand they do not belong to', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
    ]);

    // Do NOT attach exhibitor to brand

    $this->actingAs($exhibitor);

    $response = $this->putJson("{$this->baseUrl}/{$brand->slug}/profile", [
        'company_name' => 'Should Not Work',
    ]);

    $response->assertForbidden();
});

test('viewing nonexistent brand-event returns 404', function () {
    $response = $this->getJson("{$this->baseUrl}/999999");

    $response->assertNotFound();
});

// ============================================================
// Global Brand Search
// ============================================================

test('brand search requires query parameter', function () {
    $response = $this->getJson('/api/brands/search');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

test('brand search with wildcard returns all brands', function () {
    Brand::factory()->count(3)->create();

    $response = $this->getJson('/api/brands/search?q=*');

    $response->assertSuccessful();
});

// ============================================================
// Exhibitor Dashboard
// ============================================================

test('exhibitor can access dashboard', function () {
    $exhibitor = User::factory()->create([
        'name' => 'John Doe',
        'phone' => '08123456789',
        'email_verified_at' => now(),
    ]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create();
    $brand->users()->attach($exhibitor->id);

    $this->actingAs($exhibitor);

    $response = $this->getJson('/api/exhibitor/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'profile_complete',
                'brand_events',
            ],
        ]);
});

test('dashboard only shows brand events from active events', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $activeEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);
    $inactiveEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
    ]);

    $brand = Brand::factory()->create();
    $brand->users()->attach($exhibitor->id);

    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $activeEvent->id,
    ]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $inactiveEvent->id,
    ]);

    $this->actingAs($exhibitor);

    $response = $this->getJson('/api/exhibitor/dashboard');

    $response->assertSuccessful();
    $eventIds = collect($response->json('data.brand_events'))->pluck('event.id')->all();
    expect($eventIds)->toContain($activeEvent->id);
    expect($eventIds)->not->toContain($inactiveEvent->id);
});

test('brand show returns custom fields only from active event project', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    $activeEvent = Event::factory()->create(['project_id' => $projectA->id, 'is_active' => true]);
    $inactiveEvent = Event::factory()->create(['project_id' => $projectB->id, 'is_active' => false]);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $activeEvent->id]);
    BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $inactiveEvent->id]);

    CustomField::factory()->brand($projectA)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target_a',
        'type' => CustomField::TYPE_TEXT,
    ]);
    CustomField::factory()->brand($projectB)->create([
        'label' => ['en' => 'Legacy Field'],
        'key' => 'legacy_b',
        'type' => CustomField::TYPE_TEXT,
    ]);

    $response = $this->getJson("/api/brands/{$brand->slug}");

    $response->assertSuccessful();
    $keys = collect($response->json('custom_field_definitions'))->pluck('key')->all();
    expect($keys)->toContain('buyer_target_a');
    expect($keys)->not->toContain('legacy_b');
});

test('brand show de-duplicates custom fields with the same label', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $event->id]);

    CustomField::factory()->brand($project)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target_1',
        'type' => CustomField::TYPE_TEXT,
    ]);
    CustomField::factory()->brand($project)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target_2',
        'type' => CustomField::TYPE_TEXT,
    ]);

    $response = $this->getJson("/api/brands/{$brand->slug}");

    $response->assertSuccessful();
    $labels = collect($response->json('custom_field_definitions'))->pluck('label');
    expect($labels->filter(fn ($l) => str_contains(json_encode($l), 'Buyer Target'))->count())->toBe(1);
});

test('brand update preserves custom field values from other projects', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    $activeEvent = Event::factory()->create(['project_id' => $projectA->id, 'is_active' => true]);
    $inactiveEvent = Event::factory()->create(['project_id' => $projectB->id, 'is_active' => false]);

    $brand = Brand::factory()->create([
        'custom_fields' => ['legacy_b' => 'keep-me'],
    ]);
    BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $activeEvent->id]);
    BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $inactiveEvent->id]);

    CustomField::factory()->brand($projectA)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target_a',
        'type' => CustomField::TYPE_TEXT,
    ]);
    CustomField::factory()->brand($projectB)->create([
        'label' => ['en' => 'Legacy Field'],
        'key' => 'legacy_b',
        'type' => CustomField::TYPE_TEXT,
    ]);

    $response = $this->putJson("/api/brands/{$brand->slug}", [
        'project_custom_fields' => ['buyer_target_a' => 'new-value'],
    ]);

    $response->assertSuccessful();
    $brand->refresh();
    expect($brand->custom_fields['buyer_target_a'])->toBe('new-value');
    expect($brand->custom_fields['legacy_b'])->toBe('keep-me');
});

test('dashboard flags booth primary and names the owner for shared booths', function () {
    $this->event->update(['is_active' => true]);

    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $primaryBrand = Brand::factory()->create(['name' => 'Primary Brand']);
    $secondaryBrand = Brand::factory()->create(['name' => 'Secondary Brand']);
    $primaryBrand->users()->attach($exhibitor->id);
    $secondaryBrand->users()->attach($exhibitor->id);

    $primaryBe = BrandEvent::factory()->create([
        'brand_id' => $primaryBrand->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A-1',
    ]);
    $secondaryBe = BrandEvent::factory()->create([
        'brand_id' => $secondaryBrand->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A-1',
    ]);

    $this->actingAs($exhibitor);
    $response = $this->getJson('/api/exhibitor/dashboard');

    $response->assertSuccessful();
    $byId = collect($response->json('data.brand_events'))->keyBy('brand_event_id');
    expect($byId[$primaryBe->id]['is_booth_primary'])->toBeTrue();
    expect($byId[$primaryBe->id]['booth_primary_brand_name'])->toBeNull();
    expect($byId[$secondaryBe->id]['is_booth_primary'])->toBeFalse();
    expect($byId[$secondaryBe->id]['booth_primary_brand_name'])->toBe('Primary Brand');
});

test('only the booth primary can update booth fields and submit documents', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $primaryBrand = Brand::factory()->create();
    $secondaryBrand = Brand::factory()->create();
    $primaryBrand->users()->attach($exhibitor->id);
    $secondaryBrand->users()->attach($exhibitor->id);

    $primaryBe = BrandEvent::factory()->create([
        'brand_id' => $primaryBrand->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A-1',
    ]);
    $secondaryBe = BrandEvent::factory()->create([
        'brand_id' => $secondaryBrand->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A-1',
    ]);

    $this->actingAs($exhibitor);

    // Primary can write booth fields.
    $this->putJson(
        "/api/exhibitor/brands/{$primaryBrand->slug}/events/{$primaryBe->id}/booth-fields",
        ['fascia_name' => 'HELLO']
    )->assertSuccessful();

    // Non-primary is blocked on booth fields.
    $this->putJson(
        "/api/exhibitor/brands/{$secondaryBrand->slug}/events/{$secondaryBe->id}/booth-fields",
        ['fascia_name' => 'HELLO']
    )->assertStatus(403)->assertJson(['error_code' => 'BOOTH_NOT_PRIMARY']);

    // Non-primary is blocked on document submission (guard runs before lookup).
    $this->postJson(
        "/api/exhibitor/brands/{$secondaryBrand->slug}/events/{$secondaryBe->id}/documents/any-ulid",
        []
    )->assertStatus(403)->assertJson(['error_code' => 'BOOTH_NOT_PRIMARY']);
});

test('brand event without a booth number is always its own primary', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create();
    $brand->users()->attach($exhibitor->id);
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'booth_number' => null,
    ]);

    $this->actingAs($exhibitor);

    $this->putJson(
        "/api/exhibitor/brands/{$brand->slug}/events/{$brandEvent->id}/booth-fields",
        ['fascia_name' => 'SOLO']
    )->assertSuccessful();

    expect($brandEvent->fresh()->isBoothPrimary())->toBeTrue();
});

test('exhibitor can list their brands', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand1 = Brand::factory()->create();
    $brand2 = Brand::factory()->create();
    $otherBrand = Brand::factory()->create();

    $brand1->users()->attach($exhibitor->id);
    $brand2->users()->attach($exhibitor->id);
    // otherBrand NOT attached

    $this->actingAs($exhibitor);

    $response = $this->getJson('/api/exhibitor/brands');

    $response->assertSuccessful();
    expect(count($response->json('data')))->toBe(2);
});

test('exhibitor can view brand detail', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create([
        'name' => 'My Brand',
        'company_name' => 'My Company',
    ]);
    $brand->users()->attach($exhibitor->id);

    $this->actingAs($exhibitor);

    $response = $this->getJson("/api/exhibitor/brands/{$brand->slug}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'company_name'],
        ]);

    expect($response->json('data.name'))->toBe('My Brand');
});

test('exhibitor cannot view brand they do not belong to', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $otherBrand = Brand::factory()->create();
    // NOT attached

    $this->actingAs($exhibitor);

    $response = $this->getJson("/api/exhibitor/brands/{$otherBrand->slug}");

    $response->assertNotFound();
});

test('exhibitor can update their brand', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create(['name' => 'Old Name']);
    $brand->users()->attach($exhibitor->id);

    $this->actingAs($exhibitor);

    $response = $this->putJson("/api/exhibitor/brands/{$brand->slug}", [
        'name' => 'New Name',
        'company_name' => 'New Company',
    ]);

    $response->assertSuccessful();

    $brand->refresh();
    expect($brand->name)->toBe('New Name');
    expect($brand->company_name)->toBe('New Company');
});

test('exhibitor cannot update brand they do not belong to', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $otherBrand = Brand::factory()->create();
    // NOT attached

    $this->actingAs($exhibitor);

    $response = $this->putJson("/api/exhibitor/brands/{$otherBrand->slug}", [
        'name' => 'Hijacked Name',
    ]);

    $response->assertNotFound();
});

// ============================================================
// Brand Model Behavior
// ============================================================

test('brand generates slug automatically from name', function () {
    $brand = Brand::factory()->create(['name' => 'My Awesome Brand']);

    expect($brand->slug)->toBe('my-awesome-brand');
});

test('brand generates unique slug when duplicate name exists', function () {
    Brand::factory()->create(['name' => 'Duplicate Name']);
    $second = Brand::factory()->create(['name' => 'Duplicate Name']);

    expect($second->slug)->toBe('duplicate-name-1');
});

test('brand generates ulid on creation', function () {
    $brand = Brand::factory()->create();

    expect($brand->ulid)->not->toBeNull();
    expect(strlen($brand->ulid))->toBe(26);
});

test('brand sets created_by to authenticated user', function () {
    $brand = Brand::factory()->create();

    expect($brand->created_by)->toBe($this->user->id);
});
