<?php

use App\Models\Event;
use App\Models\Partner;
use App\Models\PartnerCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = ['partners.create', 'partners.read', 'partners.update', 'partners.delete'];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $staffRole->syncPermissions($permissions);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create([
        'status' => 'active',
        'username' => 'test-project',
    ]);

    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'test-event',
    ]);

    $this->categoryBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/partner-categories";
});

// ============================================================
// Global Partner CRUD
// ============================================================

test('staff can list partners', function () {
    Partner::factory()->count(3)->create();

    $response = $this->getJson('/api/partners');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBe(3);
});

test('staff can create a partner', function () {
    $response = $this->postJson('/api/partners', [
        'name' => 'Test Partner',
        'website_url' => 'https://example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Test Partner');

    $this->assertDatabaseHas('partners', ['name' => 'Test Partner']);
});

test('staff can update a partner', function () {
    $partner = Partner::factory()->create();

    $response = $this->putJson("/api/partners/{$partner->slug}", [
        'name' => 'Updated Partner',
        'website_url' => 'https://updated.com',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Partner');
});

test('staff can delete a partner', function () {
    $partner = Partner::factory()->create();

    $response = $this->deleteJson("/api/partners/{$partner->slug}");

    $response->assertSuccessful();
    $this->assertSoftDeleted('partners', ['id' => $partner->id]);
});

test('staff can search partners', function () {
    Partner::factory()->create(['name' => 'Detik Media']);
    Partner::factory()->create(['name' => 'Kompas']);

    $response = $this->getJson('/api/partners/search?q=Detik');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Detik Media');
})->skip(env('DB_CONNECTION', 'sqlite') === 'sqlite', 'ilike requires PostgreSQL');

test('staff can show a partner', function () {
    $partner = Partner::factory()->create();

    $response = $this->getJson("/api/partners/{$partner->slug}");

    $response->assertSuccessful()
        ->assertJsonPath('data.name', $partner->name);
});

// ============================================================
// Trash
// ============================================================

test('staff can list trashed partners', function () {
    $partner = Partner::factory()->create();
    $partner->delete();

    $response = $this->getJson('/api/partners-trash');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(1);
});

test('staff can restore a trashed partner', function () {
    $partner = Partner::factory()->create();
    $partner->delete();

    $response = $this->postJson("/api/partners-trash/{$partner->id}/restore");

    $response->assertSuccessful();
    expect(Partner::find($partner->id))->not->toBeNull();
});

test('staff can force delete a trashed partner', function () {
    $partner = Partner::factory()->create();
    $partner->delete();

    $response = $this->deleteJson("/api/partners-trash/{$partner->id}");

    $response->assertSuccessful();
    expect(Partner::withTrashed()->find($partner->id))->toBeNull();
});

// ============================================================
// Partner Categories
// ============================================================

test('staff can list partner categories for an event', function () {
    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Media Partners',
    ]);

    $response = $this->getJson($this->categoryBase);

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Media Partners');
});

test('staff can create a partner category', function () {
    $response = $this->postJson($this->categoryBase, [
        'name' => 'Supported by',
        'no_container' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Supported by')
        ->assertJsonPath('data.no_container', true);
});

test('staff can update a partner category', function () {
    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Old Name',
    ]);

    $response = $this->putJson("{$this->categoryBase}/{$category->slug}", [
        'name' => 'New Name',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name');
});

test('staff can delete a partner category', function () {
    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'To Delete',
    ]);

    $response = $this->deleteJson("{$this->categoryBase}/{$category->slug}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('partner_categories', ['id' => $category->id]);
});

test('staff can add a partner to a category', function () {
    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Media Partners',
    ]);

    $partner = Partner::factory()->create();

    $response = $this->postJson("{$this->categoryBase}/{$category->slug}/partners", [
        'partner_id' => $partner->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', $partner->name);

    expect($category->partners()->count())->toBe(1);
});

test('staff can add a new partner by name to a category', function () {
    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Sponsors',
    ]);

    $response = $this->postJson("{$this->categoryBase}/{$category->slug}/partners", [
        'partner_name' => 'Brand New Partner',
        'website_url' => 'https://newpartner.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Brand New Partner');

    $this->assertDatabaseHas('partners', ['name' => 'Brand New Partner']);
    expect($category->partners()->count())->toBe(1);
});

test('staff can add a new partner with a logo to a category', function () {
    Storage::fake('local');
    Storage::fake('public');

    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Sponsors',
    ]);

    $folder = uniqid('tmp-', true);
    $file = UploadedFile::fake()->image('logo.png', 600, 400);
    Storage::disk('local')->putFileAs("tmp/uploads/{$folder}", $file, 'logo.png');
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => 'logo.png',
        'mime_type' => 'image/png',
        'size' => 1000,
        'uploaded_at' => now()->toISOString(),
    ]));

    $response = $this->postJson("{$this->categoryBase}/{$category->slug}/partners", [
        'partner_name' => 'Logo Partner',
        'tmp_partner_logo' => $folder,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Logo Partner');

    $partner = Partner::where('name', 'Logo Partner')->first();
    expect($partner)->not->toBeNull();
    expect($partner->getMedia('partner_logo'))->toHaveCount(1);
    expect($category->partners()->count())->toBe(1);
});

test('staff can remove a partner from a category', function () {
    $category = PartnerCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Media Partners',
    ]);

    $partner = Partner::factory()->create();
    $category->partners()->attach($partner->id, ['order_column' => 1]);

    $pivotId = $category->partners()->first()->pivot->id;

    $response = $this->deleteJson("{$this->categoryBase}/{$category->slug}/partners/{$pivotId}");

    $response->assertSuccessful();
    expect($category->partners()->count())->toBe(0);
});

test('staff can reorder partner categories', function () {
    $cat1 = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Cat 1']);
    $cat2 = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Cat 2']);
    $cat3 = PartnerCategory::create(['event_id' => $this->event->id, 'name' => 'Cat 3']);

    $response = $this->postJson("{$this->categoryBase}/update-order", [
        'order' => [$cat3->id, $cat1->id, $cat2->id],
    ]);

    $response->assertSuccessful();

    expect(PartnerCategory::find($cat3->id)->order_column)->toBe(1);
    expect(PartnerCategory::find($cat1->id)->order_column)->toBe(2);
    expect(PartnerCategory::find($cat2->id)->order_column)->toBe(3);
});

test('staff can copy partners from another event', function () {
    // Source event with categories and partners
    $sourceEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'source-event',
    ]);

    $sourceCat = PartnerCategory::create([
        'event_id' => $sourceEvent->id,
        'name' => 'Media Partners',
    ]);

    $partner1 = Partner::factory()->create();
    $partner2 = Partner::factory()->create();
    $sourceCat->partners()->attach($partner1->id, ['order_column' => 1]);
    $sourceCat->partners()->attach($partner2->id, ['order_column' => 2]);

    $response = $this->postJson("{$this->categoryBase}/copy-from-event", [
        'source_event_id' => $sourceEvent->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('copied_categories', 1)
        ->assertJsonPath('copied_partners', 2);

    expect($this->event->partnerCategories()->count())->toBe(1);
    expect($this->event->partnerCategories()->first()->partners()->count())->toBe(2);
});
