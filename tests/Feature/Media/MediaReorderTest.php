<?php

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    Permission::firstOrCreate(['name' => 'admin.media', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'hotels.update', 'guard_name' => 'web']);

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('master');
});

function addHotelGalleryImage(Hotel $hotel, int $uploaderId): Media
{
    return $hotel
        ->addMedia(UploadedFile::fake()->image('gallery-'.uniqid().'.png', 10, 10))
        ->withCustomProperties(['uploaded_by' => $uploaderId])
        ->toMediaCollection('gallery');
}

it('reorders a hotel gallery and persists the new order', function () {
    $this->actingAs($this->admin);

    $hotel = Hotel::factory()->create();
    $a = addHotelGalleryImage($hotel, $this->admin->id);
    $b = addHotelGalleryImage($hotel, $this->admin->id);
    $c = addHotelGalleryImage($hotel, $this->admin->id);

    $this->postJson('/api/media/reorder', [
        'media_ids' => [$c->id, $b->id, $a->id],
    ])
        ->assertOk()
        ->assertJson(['message' => 'Order updated']);

    expect($hotel->fresh()->getMedia('gallery')->pluck('id')->all())
        ->toBe([$c->id, $b->id, $a->id]);
});

it('allows reorder for a user who can update the owning model', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $hotel = Hotel::factory()->create();
    $a = addHotelGalleryImage($hotel, $owner->id);
    $b = addHotelGalleryImage($hotel, $owner->id);

    $manager = User::factory()->create(['email_verified_at' => now()]);
    $manager->givePermissionTo('hotels.update');
    $this->actingAs($manager);

    $this->postJson('/api/media/reorder', [
        'media_ids' => [$b->id, $a->id],
    ])->assertOk();

    expect($hotel->fresh()->getMedia('gallery')->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('rejects reorder spanning different owners', function () {
    $this->actingAs($this->admin);

    $hotelA = Hotel::factory()->create();
    $hotelB = Hotel::factory()->create();
    $mediaA = addHotelGalleryImage($hotelA, $this->admin->id);
    $mediaB = addHotelGalleryImage($hotelB, $this->admin->id);

    $this->postJson('/api/media/reorder', [
        'media_ids' => [$mediaA->id, $mediaB->id],
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'All media must belong to the same model and collection');
});

it('forbids reorder for a user who is neither uploader nor admin', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $hotel = Hotel::factory()->create();
    $a = addHotelGalleryImage($hotel, $owner->id);
    $b = addHotelGalleryImage($hotel, $owner->id);

    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($stranger);

    $this->postJson('/api/media/reorder', [
        'media_ids' => [$b->id, $a->id],
    ])
        ->assertStatus(403)
        ->assertJsonPath('message', 'Unauthorized to reorder this media');
});

it('validates that media_ids is required and non-empty', function () {
    $this->actingAs($this->admin);

    $this->postJson('/api/media/reorder', ['media_ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['media_ids']);

    $this->postJson('/api/media/reorder', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['media_ids']);
});

it('rejects reorder when a media id does not exist', function () {
    $this->actingAs($this->admin);

    $hotel = Hotel::factory()->create();
    $a = addHotelGalleryImage($hotel, $this->admin->id);

    $this->postJson('/api/media/reorder', [
        'media_ids' => [$a->id, 999999],
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'One or more media not found');
});
