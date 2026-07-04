<?php

use App\Models\Project;
use App\Models\User;
use App\Support\OgPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'projects.update', 'guard_name' => 'web']);
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create([
        'status' => 'active',
        'username' => 'acme',
    ]);
});

function seedTmpOgUpload(string $folder, int $width = 1600, int $height = 900): void
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image, null, 90);
    $contents = ob_get_clean();
    imagedestroy($image);

    Storage::disk('local')->put("tmp/uploads/{$folder}/og.jpg", $contents);
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => 'og.jpg',
    ]));
}

test('show returns a stable payload with all canonical page keys', function () {
    $response = $this->getJson('/api/projects/acme/og-images')
        ->assertSuccessful()
        ->assertJsonPath('project_id', $this->project->id)
        ->assertJsonPath('website_url', null);

    foreach (OgPages::KEYS as $key) {
        $response->assertJsonPath("pages.{$key}.title", null)
            ->assertJsonPath("pages.{$key}.image", null);
    }
});

test('update stores per-page titles and descriptions in settings', function () {
    $this->putJson('/api/projects/acme/og-images', [
        'pages' => [
            'home' => ['title' => 'Acme Expo 2026', 'description' => 'The biggest expo.'],
            'brands' => ['title' => 'Exhibitors'],
        ],
    ])->assertSuccessful()
        ->assertJsonPath('pages.home.title', 'Acme Expo 2026')
        ->assertJsonPath('pages.brands.title', 'Exhibitors');

    $settings = $this->project->fresh()->settings;
    expect(data_get($settings, 'website_settings.og_pages.home.description'))->toBe('The biggest expo.');
});

test('update rejects unknown page keys', function () {
    $this->putJson('/api/projects/acme/og-images', [
        'pages' => ['not-a-page' => ['title' => 'Nope']],
    ])->assertUnprocessable();

    $this->putJson('/api/projects/acme/og-images', [
        'tmp_images' => ['not-a-page' => 'tmp-x'],
    ])->assertUnprocessable();
});

test('update moves a tmp image into the page collection cropped to 1200x630', function () {
    seedTmpOgUpload('tmp-ogtest', 1600, 900);

    $this->putJson('/api/projects/acme/og-images', [
        'tmp_images' => ['home' => 'tmp-ogtest'],
    ])->assertSuccessful()
        ->assertJsonPath('pages.home.image.width', 1200)
        ->assertJsonPath('pages.home.image.height', 630);

    $project = $this->project->fresh();
    $media = $project->getFirstMedia(OgPages::collectionFor('home'));

    expect($media)->not->toBeNull();
    [$width, $height] = getimagesize($media->getPath());
    expect($width)->toBe(1200);
    expect($height)->toBe(630);
    expect(Storage::disk('local')->exists('tmp/uploads/tmp-ogtest'))->toBeFalse();
});

test('update never upscales a small image', function () {
    seedTmpOgUpload('tmp-ogsmall', 800, 400);

    $this->putJson('/api/projects/acme/og-images', [
        'tmp_images' => ['contact' => 'tmp-ogsmall'],
    ])->assertSuccessful();

    $media = $this->project->fresh()->getFirstMedia(OgPages::collectionFor('contact'));
    [$width, $height] = getimagesize($media->getPath());
    expect($width)->toBe(800);
    expect($height)->toBe(400);
});

test('update with delete flag clears the page image', function () {
    seedTmpOgUpload('tmp-ogdelete');
    $this->putJson('/api/projects/acme/og-images', [
        'tmp_images' => ['brands' => 'tmp-ogdelete'],
    ])->assertSuccessful();

    $this->putJson('/api/projects/acme/og-images', [
        'delete_images' => ['brands' => true],
    ])->assertSuccessful()
        ->assertJsonPath('pages.brands.image', null);

    expect($this->project->fresh()->getFirstMedia(OgPages::collectionFor('brands')))->toBeNull();
});

test('update is forbidden for users without project access', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($outsider);

    $this->putJson('/api/projects/acme/og-images', [
        'pages' => ['home' => ['title' => 'Hacked']],
    ])->assertForbidden();
});
