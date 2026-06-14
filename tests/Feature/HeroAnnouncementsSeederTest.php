<?php

use App\Models\ApiConsumer;
use App\Models\Project;
use Database\Seeders\HeroAnnouncementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function apiKeyHeaders(): array
{
    ApiConsumer::factory()->create(['api_key' => 'pk_test_hero', 'is_active' => true]);

    return ['X-API-Key' => 'pk_test_hero'];
}

test('seeds hero-announcement banners surfaced by the public endpoint in order', function () {
    Project::factory()->create(['username' => 'cbe']);

    $this->seed(HeroAnnouncementsSeeder::class);

    $this->withHeaders(apiKeyHeaders())
        ->getJson('/api/public/banners?project_slug=cbe&placement=hero-announcement')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.subHeadline', 'Space is still available for exhibitors')
        ->assertJsonPath('data.0.cta.link', '/book-space')
        ->assertJsonPath('data.1.subHeadline', 'Visitor Registration Is Now Open!')
        ->assertJsonPath('data.1.cta.link', '/ticket');
});

test('only seeds projects that exist and matches their item count', function () {
    Project::factory()->create(['username' => 'megabuild']);

    $this->seed(HeroAnnouncementsSeeder::class);

    $megabuild = Project::where('username', 'megabuild')->first();
    expect($megabuild->banners()->where('placement', 'hero-announcement')->count())->toBe(1);
});

test('is idempotent - re-running creates no duplicates', function () {
    $project = Project::factory()->create(['username' => 'cbe']);

    $this->seed(HeroAnnouncementsSeeder::class);
    $this->seed(HeroAnnouncementsSeeder::class);

    expect($project->banners()->where('placement', 'hero-announcement')->count())->toBe(2);
});
