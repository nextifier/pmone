<?php

use App\Jobs\ExtractOpenGraphMetadata;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('command dispatches jobs for short links without metadata', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create short links: some with metadata, some without
    $linkWithoutMetadata1 = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'no-metadata-1',
        'destination_url' => 'https://example.com/1',
        'is_active' => true,
    ]);

    $linkWithoutMetadata2 = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'no-metadata-2',
        'destination_url' => 'https://example.com/2',
        'is_active' => true,
    ]);

    $linkWithMetadata = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'with-metadata',
        'destination_url' => 'https://example.com/3',
        'is_active' => true,
        'og_title' => 'Already Has Metadata',
        'og_description' => 'Description',
        'og_image' => 'https://example.com/image.jpg',
    ]);

    // Clear queue from observer
    Queue::fake();

    // Run command
    $this->artisan('short-links:extract-metadata')
        ->expectsConfirmation('Do you want to proceed?', 'yes')
        ->assertSuccessful();

    // Should dispatch jobs only for links without metadata
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 2);
    Queue::assertPushed(ExtractOpenGraphMetadata::class, function ($job) use ($linkWithoutMetadata1) {
        return $job->shortLinkId === $linkWithoutMetadata1->id;
    });
    Queue::assertPushed(ExtractOpenGraphMetadata::class, function ($job) use ($linkWithoutMetadata2) {
        return $job->shortLinkId === $linkWithoutMetadata2->id;
    });
});

test('command with force option re-extracts metadata for all links', function () {
    Queue::fake();

    $user = User::factory()->create();

    $link1 = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'link-1',
        'destination_url' => 'https://example.com/1',
        'is_active' => true,
        'og_title' => 'Existing Title',
    ]);

    $link2 = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'link-2',
        'destination_url' => 'https://example.com/2',
        'is_active' => true,
    ]);

    // Clear queue from observer
    Queue::fake();

    // Run command with --force
    $this->artisan('short-links:extract-metadata --force')
        ->expectsConfirmation('Do you want to proceed?', 'yes')
        ->assertSuccessful();

    // Should dispatch jobs for all links
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 2);
});

test('command with active-only option filters inactive links', function () {
    Queue::fake();

    $user = User::factory()->create();

    $activeLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'active',
        'destination_url' => 'https://example.com/active',
        'is_active' => true,
    ]);

    $inactiveLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'inactive',
        'destination_url' => 'https://example.com/inactive',
        'is_active' => false,
    ]);

    // Clear queue from observer
    Queue::fake();

    // Run command with --active-only
    $this->artisan('short-links:extract-metadata --active-only')
        ->expectsConfirmation('Do you want to proceed?', 'yes')
        ->assertSuccessful();

    // Should only dispatch job for active link
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 1);
    Queue::assertPushed(ExtractOpenGraphMetadata::class, function ($job) use ($activeLink) {
        return $job->shortLinkId === $activeLink->id;
    });
});

test('command with limit option processes limited number of links', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create 5 links without metadata
    for ($i = 1; $i <= 5; $i++) {
        ShortLink::create([
            'user_id' => $user->id,
            'slug' => "link-{$i}",
            'destination_url' => "https://example.com/{$i}",
            'is_active' => true,
        ]);
    }

    // Clear queue from observer
    Queue::fake();

    // Run command with --limit=3
    $this->artisan('short-links:extract-metadata --limit=3')
        ->expectsConfirmation('Do you want to proceed?', 'yes')
        ->assertSuccessful();

    // Should only dispatch 3 jobs
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 3);
});

test('command handles empty result gracefully', function () {
    Queue::fake();

    // No short links in database

    // Run command
    $this->artisan('short-links:extract-metadata')
        ->expectsOutput('No short links found to process.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('command can be cancelled by user', function () {
    Queue::fake();

    $user = User::factory()->create();

    ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'test',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    // Clear queue from observer
    Queue::fake();

    // Run command and answer 'no' to confirmation
    $this->artisan('short-links:extract-metadata')
        ->expectsConfirmation('Do you want to proceed?', 'no')
        ->expectsOutput('Operation cancelled.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('command combines multiple options correctly', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create mix of active/inactive links with/without metadata
    $targetLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'active-no-meta',
        'destination_url' => 'https://example.com/target',
        'is_active' => true,
    ]);

    ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'active-with-meta',
        'destination_url' => 'https://example.com/skip',
        'is_active' => true,
        'og_title' => 'Has metadata',
    ]);

    ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'inactive-no-meta',
        'destination_url' => 'https://example.com/inactive',
        'is_active' => false,
    ]);

    // Clear queue from observer
    Queue::fake();

    // Run with --active-only (should process only the first link)
    $this->artisan('short-links:extract-metadata --active-only')
        ->expectsConfirmation('Do you want to proceed?', 'yes')
        ->assertSuccessful();

    Queue::assertPushed(ExtractOpenGraphMetadata::class, 1);
    Queue::assertPushed(ExtractOpenGraphMetadata::class, function ($job) use ($targetLink) {
        return $job->shortLinkId === $targetLink->id;
    });
});
