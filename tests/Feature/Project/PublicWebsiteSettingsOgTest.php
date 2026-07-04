<?php

use App\Models\ApiConsumer;
use App\Models\Project;
use App\Models\User;
use App\Support\OgPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_og_pages', 'is_active' => true]);

    $this->project = Project::factory()->create([
        'status' => 'active',
        'username' => 'acme',
    ]);

    $this->endpoint = '/api/public/projects/acme/website-settings';
});

test('og_pages is empty for an unconfigured project', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_og_pages'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.og_pages', []);
});

test('og_pages exposes configured keys with title, description, and image', function () {
    $settings = $this->project->settings ?? [];
    data_set($settings, 'website_settings.og_pages.home', [
        'title' => 'Acme Expo 2026',
        'description' => 'The biggest expo.',
    ]);
    $this->project->update(['settings' => $settings]);

    $this->project
        ->addMedia(UploadedFile::fake()->image('og-home.jpg', 1200, 630))
        ->withCustomProperties(['width' => 1200, 'height' => 630])
        ->toMediaCollection(OgPages::collectionFor('home'));

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_og_pages'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.og_pages.home.title', 'Acme Expo 2026')
        ->assertJsonPath('data.settings.og_pages.home.image.width', 1200)
        ->assertJsonPath('data.settings.og_pages.home.image.height', 630);

    expect($response->json('data.settings.og_pages.home.image.url'))->not->toBeEmpty();
    expect($response->json('data.settings.og_pages'))->not->toHaveKey('brands');
});

test('title-only pages appear with a null image', function () {
    $settings = $this->project->settings ?? [];
    data_set($settings, 'website_settings.og_pages.tickets.title', 'Get Tickets');
    $this->project->update(['settings' => $settings]);

    $this->withHeaders(['X-API-Key' => 'pk_test_og_pages'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.og_pages.tickets.title', 'Get Tickets')
        ->assertJsonPath('data.settings.og_pages.tickets.image', null);
});

test('saving OG settings busts the public website-settings cache', function () {
    Permission::firstOrCreate(['name' => 'projects.update', 'guard_name' => 'web']);
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');

    $headers = ['X-API-Key' => 'pk_test_og_pages'];

    $this->withHeaders($headers)->getJson($this->endpoint)
        ->assertJsonPath('data.settings.og_pages', []);

    $this->actingAs($user)
        ->putJson('/api/projects/acme/og-images', [
            'pages' => ['home' => ['title' => 'Fresh title']],
        ])->assertSuccessful();

    $this->withHeaders($headers)->getJson($this->endpoint)
        ->assertJsonPath('data.settings.og_pages.home.title', 'Fresh title');
});
