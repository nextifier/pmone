<?php

use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

// Helper to create authenticated user with permissions
function createLinkPageUser(array $permissions = []): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('user');

    if (! empty($permissions)) {
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
    }

    return $user;
}

// ─── CRUD ───────────────────────────────────────────────────────────

test('can create a link page', function () {
    $user = createLinkPageUser(['link_pages.create', 'link_pages.read']);

    $response = $this->actingAs($user)->postJson('/api/link-pages', [
        'title' => 'My Links',
        'slug' => 'my-links',
        'description' => 'A collection of useful links',
        'visibility' => 'public',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'My Links')
        ->assertJsonPath('data.slug', 'my-links');

    $this->assertDatabaseHas('link_pages', [
        'slug' => 'my-links',
        'user_id' => $user->id,
    ]);
});

test('can view a link page', function () {
    $user = createLinkPageUser(['link_pages.read']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/link-pages/{$linkPage->slug}");

    $response->assertOk()
        ->assertJsonPath('data.slug', $linkPage->slug);
});

test('can update a link page', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/link-pages/{$linkPage->slug}", [
        'title' => 'Updated Title',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Updated Title');
});

test('can delete a link page', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.delete']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/link-pages/{$linkPage->slug}");

    $response->assertOk();
    $this->assertSoftDeleted('link_pages', ['id' => $linkPage->id]);
});

test('can list link pages', function () {
    $user = createLinkPageUser(['link_pages.read']);
    LinkPage::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/link-pages?client_only=true');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

// ─── Slug Validation ────────────────────────────────────────────────

test('slug must be unique across link pages', function () {
    $user = createLinkPageUser(['link_pages.create']);
    LinkPage::factory()->create(['slug' => 'taken-slug']);

    $response = $this->actingAs($user)->postJson('/api/link-pages', [
        'title' => 'Test',
        'slug' => 'taken-slug',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

test('link page slug cannot conflict with existing username', function () {
    User::factory()->create(['username' => 'johnsmith']);
    $user = createLinkPageUser(['link_pages.create']);

    $response = $this->actingAs($user)->postJson('/api/link-pages', [
        'title' => 'Test',
        'slug' => 'johnsmith',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

test('link page slug cannot conflict with existing short link', function () {
    $user = createLinkPageUser(['link_pages.create']);
    ShortLink::factory()->create(['slug' => 'existing-link']);

    $response = $this->actingAs($user)->postJson('/api/link-pages', [
        'title' => 'Test',
        'slug' => 'existing-link',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

test('short link slug cannot conflict with existing link page', function () {
    $user = createLinkPageUser(['short_links.create']);
    LinkPage::factory()->create(['slug' => 'my-page']);

    $response = $this->actingAs($user)->postJson('/api/short-links', [
        'slug' => 'my-page',
        'destination_url' => 'https://example.com',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

// ─── Authorization ──────────────────────────────────────────────────

test('non-owner cannot update link page', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->putJson("/api/link-pages/{$linkPage->slug}", [
        'title' => 'Hacked',
    ]);

    $response->assertForbidden();
});

test('admin can update any link page', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $admin = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $admin->assignRole('admin');
    $linkPage = LinkPage::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($admin)->putJson("/api/link-pages/{$linkPage->slug}", [
        'title' => 'Admin Updated',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Admin Updated');
});

// ─── Trash / Restore ────────────────────────────────────────────────

test('can list trashed link pages', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.delete']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $linkPage->delete();

    $response = $this->actingAs($user)->getJson('/api/link-pages/trash?client_only=true');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can restore a trashed link page', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.delete']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $linkPage->delete();

    $response = $this->actingAs($user)->postJson("/api/link-pages/trash/{$linkPage->id}/restore");

    $response->assertOk();
    $this->assertDatabaseHas('link_pages', [
        'id' => $linkPage->id,
        'deleted_at' => null,
    ]);
});

test('can force delete a trashed link page', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.delete']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $linkPage->delete();

    $response = $this->actingAs($user)->deleteJson("/api/link-pages/trash/{$linkPage->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('link_pages', ['id' => $linkPage->id]);
});

// ─── Items CRUD ─────────────────────────────────────────────────────

test('can add item to link page', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/api/link-pages/{$linkPage->slug}/items", [
        'label' => 'Google',
        'url' => 'https://google.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.label', 'Google');

    $this->assertDatabaseHas('link_page_items', [
        'link_page_id' => $linkPage->id,
        'label' => 'Google',
    ]);
});

test('can update an item', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $item = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id]);

    $response = $this->actingAs($user)->putJson("/api/link-pages/{$linkPage->slug}/items/{$item->id}", [
        'label' => 'Updated Label',
        'url' => 'https://updated.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.label', 'Updated Label');
});

test('can delete an item', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $item = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id]);

    $response = $this->actingAs($user)->deleteJson("/api/link-pages/{$linkPage->slug}/items/{$item->id}");

    $response->assertOk();
    $this->assertSoftDeleted('link_page_items', ['id' => $item->id]);
});

test('can reorder items', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $item1 = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id, 'sort_order' => 0]);
    $item2 = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id, 'sort_order' => 1]);

    $response = $this->actingAs($user)->putJson("/api/link-pages/{$linkPage->slug}/items/reorder", [
        'orders' => [
            ['id' => $item1->id, 'order' => 1],
            ['id' => $item2->id, 'order' => 0],
        ],
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('link_page_items', ['id' => $item1->id, 'sort_order' => 1]);
    $this->assertDatabaseHas('link_page_items', ['id' => $item2->id, 'sort_order' => 0]);
});

test('can toggle item active status', function () {
    $user = createLinkPageUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $item = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id, 'is_active' => true]);

    $response = $this->actingAs($user)->patchJson("/api/link-pages/{$linkPage->slug}/items/{$item->id}/toggle");

    $response->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// ─── Resolve Slug ───────────────────────────────────────────────────

test('resolve endpoint returns linkpage type for link page slug', function () {
    $user = User::factory()->create();
    $linkPage = LinkPage::factory()->create([
        'user_id' => $user->id,
        'slug' => 'my-link-page',
        'is_active' => true,
    ]);

    LinkPageItem::factory()->count(2)->create([
        'link_page_id' => $linkPage->id,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/resolve/my-link-page');

    $response->assertOk()
        ->assertJson(['type' => 'linkpage'])
        ->assertJsonPath('data.slug', 'my-link-page');
});

test('resolve endpoint does not return inactive link pages', function () {
    $user = User::factory()->create();
    LinkPage::factory()->create([
        'user_id' => $user->id,
        'slug' => 'inactive-page',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/resolve/inactive-page');

    $response->assertNotFound();
});

test('resolve endpoint prioritizes user and shortlink over linkpage', function () {
    $user = User::factory()->create(['username' => 'test-slug', 'status' => 'active']);
    LinkPage::factory()->create([
        'user_id' => $user->id,
        'slug' => 'test-slug',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/resolve/test-slug');

    $response->assertOk()
        ->assertJson(['type' => 'user']);
});

// ─── Analytics ──────────────────────────────────────────────────────

test('can get link page analytics', function () {
    $user = createLinkPageUser(['link_pages.read']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/link-pages/{$linkPage->slug}/analytics?days=7");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => ['total_visits', 'total_clicks', 'total_items'],
                'visits_per_day',
                'item_clicks',
            ],
        ]);
});

// ─── Check Slug ─────────────────────────────────────────────────────

test('check slug returns availability', function () {
    $user = createLinkPageUser(['link_pages.read']);

    $response = $this->actingAs($user)->getJson('/api/link-pages/check-slug?slug=available-slug');

    $response->assertOk()
        ->assertJson(['available' => true]);
});

test('check slug returns unavailable for taken slug', function () {
    $user = createLinkPageUser(['link_pages.read']);
    LinkPage::factory()->create(['slug' => 'taken-slug']);

    $response = $this->actingAs($user)->getJson('/api/link-pages/check-slug?slug=taken-slug');

    $response->assertOk()
        ->assertJson(['available' => false]);
});
