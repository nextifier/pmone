<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

// Create Category Tests
test('user can create a category', function () {
    $categoryData = [
        'name' => 'Technology',
        'slug' => 'technology',
        'description' => 'Tech related posts',
        'visibility' => 'public',
        'order' => 0,
    ];

    $response = $this->postJson('/api/categories', $categoryData);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'name',
                'slug',
                'visibility',
            ],
        ]);

    $this->assertDatabaseHas('categories', [
        'name' => 'Technology',
        'slug' => 'technology',
    ]);
});

test('user can create nested category with parent', function () {
    $parent = Category::factory()->create();

    $categoryData = [
        'name' => 'Sub Category',
        'parent_id' => $parent->id,
        'visibility' => 'public',
    ];

    $response = $this->postJson('/api/categories', $categoryData);

    $response->assertSuccessful();

    $category = Category::where('name', 'Sub Category')->first();
    expect($category->parent_id)->toBe($parent->id);
    expect($category->parent->name)->toBe($parent->name);
});

test('category name is required', function () {
    $response = $this->postJson('/api/categories', [
        'visibility' => 'public',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('category slug must be unique', function () {
    Category::factory()->create(['slug' => 'existing-slug']);

    $response = $this->postJson('/api/categories', [
        'name' => 'New Category',
        'slug' => 'existing-slug',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

// Update Category Tests
test('user can update a category', function () {
    $category = Category::factory()->create([
        'name' => 'Original Name',
        'created_by' => $this->user->id,
    ]);

    $response = $this->putJson("/api/categories/{$category->slug}", [
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ]);

    $response->assertSuccessful();

    $category->refresh();
    expect($category->name)->toBe('Updated Name');
    expect($category->description)->toBe('Updated description');
});

test('user can update category parent', function () {
    $category = Category::factory()->create(['created_by' => $this->user->id]);
    $newParent = Category::factory()->create();

    $response = $this->putJson("/api/categories/{$category->slug}", [
        'name' => $category->name,
        'parent_id' => $newParent->id,
    ]);

    $response->assertSuccessful();

    $category->refresh();
    expect($category->parent_id)->toBe($newParent->id);
});

// Nested Categories Tests
test('category can have children', function () {
    $parent = Category::factory()->create();
    $child1 = Category::factory()->create(['parent_id' => $parent->id]);
    $child2 = Category::factory()->create(['parent_id' => $parent->id]);

    $parent->load('children');

    expect($parent->children)->toHaveCount(2);
    expect($parent->children->pluck('id')->toArray())->toContain($child1->id, $child2->id);
});

test('category has parent relationship', function () {
    $parent = Category::factory()->create(['name' => 'Parent Category']);
    $child = Category::factory()->create([
        'name' => 'Child Category',
        'parent_id' => $parent->id,
    ]);

    $child->load('parent');

    expect($child->parent)->not->toBeNull();
    expect($child->parent->name)->toBe('Parent Category');
});

test('root scope returns categories without parent', function () {
    $root1 = Category::factory()->create();
    $root2 = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root1->id]);

    $rootCategories = Category::root()->get();

    expect($rootCategories)->toHaveCount(2);
    expect($rootCategories->pluck('id')->toArray())->toContain($root1->id, $root2->id);
    expect($rootCategories->pluck('id')->toArray())->not->toContain($child->id);
});

// Delete & Trash Tests
test('user can soft delete a category', function () {
    $category = Category::factory()->create(['created_by' => $this->user->id]);

    $response = $this->deleteJson("/api/categories/{$category->slug}");

    $response->assertSuccessful();

    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

test('user can view trashed categories', function () {
    Category::factory()->count(2)->create(['created_by' => $this->user->id]);
    $trashedCategory = Category::factory()->create(['created_by' => $this->user->id]);
    $trashedCategory->delete();

    $response = $this->getJson('/api/categories/trash');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('user can restore a trashed category', function () {
    $category = Category::factory()->create(['created_by' => $this->user->id]);
    $category->delete();

    $response = $this->postJson("/api/categories/trash/{$category->id}/restore");

    $response->assertSuccessful();

    $category->refresh();
    expect($category->deleted_at)->toBeNull();
});

test('user can permanently delete a category', function () {
    $category = Category::factory()->create(['created_by' => $this->user->id]);
    $category->delete();

    $response = $this->deleteJson("/api/categories/trash/{$category->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

// Bulk Operations Tests
test('user can bulk delete categories', function () {
    $categories = Category::factory()->count(3)->create(['created_by' => $this->user->id]);
    $categoryIds = $categories->pluck('id')->toArray();

    $response = $this->deleteJson('/api/categories/bulk', [
        'ids' => $categoryIds,
    ]);

    $response->assertSuccessful();

    foreach ($categoryIds as $id) {
        $this->assertSoftDeleted('categories', ['id' => $id]);
    }
});

test('user can bulk restore categories', function () {
    $categories = Category::factory()->count(3)->create(['created_by' => $this->user->id]);

    foreach ($categories as $category) {
        $category->delete();
    }

    $categoryIds = $categories->pluck('id')->toArray();

    $response = $this->postJson('/api/categories/trash/restore/bulk', [
        'ids' => $categoryIds,
    ]);

    $response->assertSuccessful();

    foreach ($categoryIds as $id) {
        $category = Category::find($id);
        expect($category->deleted_at)->toBeNull();
    }
});

// Validation Tests
test('category visibility must be valid', function () {
    $response = $this->postJson('/api/categories', [
        'name' => 'Test Category',
        'visibility' => 'invalid_visibility',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['visibility']);
});

test('parent_id must exist in categories table', function () {
    $response = $this->postJson('/api/categories', [
        'name' => 'Test Category',
        'parent_id' => 99999, // Non-existent ID
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

// Filtering Tests
test('user can filter categories by visibility', function () {
    Category::factory()->create(['visibility' => 'public', 'created_by' => $this->user->id]);
    Category::factory()->create(['visibility' => 'private', 'created_by' => $this->user->id]);

    $response = $this->getJson('/api/categories?filter_visibility=public');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('user can filter root categories', function () {
    $root1 = Category::factory()->create(['created_by' => $this->user->id]);
    $root2 = Category::factory()->create(['created_by' => $this->user->id]);
    $child = Category::factory()->create([
        'parent_id' => $root1->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/categories?filter_root=1');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('user can search categories', function () {
    Category::factory()->create([
        'name' => 'Technology',
        'created_by' => $this->user->id,
    ]);
    Category::factory()->create([
        'name' => 'Business',
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/categories?filter_search=Tech');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

// Category with Posts Tests
test('category can have multiple posts', function () {
    $category = Category::factory()
        ->hasPosts(3)
        ->create();

    expect($category->posts)->toHaveCount(3);
});

test('deleting category does not delete posts', function () {
    $category = Category::factory()
        ->hasPosts(2)
        ->create(['created_by' => $this->user->id]);

    $postIds = $category->posts->pluck('id')->toArray();

    $this->deleteJson("/api/categories/{$category->slug}");

    foreach ($postIds as $postId) {
        $this->assertDatabaseHas('posts', ['id' => $postId]);
    }
});
