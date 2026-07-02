<?php

use App\Models\Event;
use App\Models\LinkPage;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Support\FileNamer\UniqueFileNamer;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * Stage a temp upload exactly like TemporaryUploadController does, so the
 * controllers' handleTemporaryUpload()/handlePosterUpload() can consume it.
 */
function stageTmpUpload(string $filename): string
{
    $file = UploadedFile::fake()->image($filename);
    $tmpId = 'tmp-'.uniqid('', true);

    Storage::disk('local')->put("tmp/uploads/{$tmpId}/{$filename}", $file->getContent());
    Storage::disk('local')->put("tmp/uploads/{$tmpId}/metadata.json", json_encode([
        'original_name' => $filename,
        'mime_type' => 'image/jpeg',
        'size' => $file->getSize(),
    ]));

    return $tmpId;
}

it('keeps the global UniqueFileNamer wired so the fix cannot silently regress', function () {
    // Guard: the whole cross-model fix hinges on this one config binding. If anyone
    // reverts it to DefaultFileNamer, same-filename replacement breaks again app-wide.
    expect(config('media-library.file_namer'))->toBe(UniqueFileNamer::class);
});

dataset('singleFileCollections', [
    'event poster_image' => [fn () => Event::factory()->create(), 'poster_image'],
    'ticket poster' => [fn () => Ticket::factory()->create(), 'poster'],
    'user profile_image' => [fn () => User::factory()->create(), 'profile_image'],
    'project branding_logo' => [fn () => Project::factory()->create(), 'branding_logo'],
    'linkpage cover_image' => [fn () => LinkPage::factory()->create(), 'cover_image'],
]);

it('replaces the file and busts the URL when a new upload shares the same filename', function (Closure $makeModel, string $collection) {
    $model = $makeModel();

    $model->addMedia(UploadedFile::fake()->image('poster.jpg'))->toMediaCollection($collection);
    $first = $model->fresh()->getFirstMedia($collection);
    $firstPath = $first->getPathRelativeToRoot();
    Storage::disk('public')->assertExists($firstPath);

    // Same original filename: this used to resolve to a byte-identical path + URL,
    // and for single-file collections the surplus-media cleanup could delete the
    // freshly written file.
    $model->addMedia(UploadedFile::fake()->image('poster.jpg'))->toMediaCollection($collection);
    $second = $model->fresh()->getFirstMedia($collection);

    expect($model->fresh()->getMedia($collection))->toHaveCount(1)
        ->and($second->file_name)->not->toBe($first->file_name)
        ->and($second->getUrl())->not->toBe($first->getUrl());

    Storage::disk('public')->assertMissing($firstPath);                       // old bytes gone
    Storage::disk('public')->assertExists($second->getPathRelativeToRoot());  // new bytes present
})->with('singleFileCollections');

it('busts the event poster URL when replaced with the same filename through the API', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);

    $event->addMedia(UploadedFile::fake()->image('poster.jpg'))->toMediaCollection('poster_image');
    $firstUrl = $event->fresh()->getFirstMediaUrl('poster_image');
    $firstPath = $event->fresh()->getFirstMedia('poster_image')->getPathRelativeToRoot();

    $this->putJson("/api/projects/{$project->username}/events/{$event->slug}", [
        'tmp_poster_image' => stageTmpUpload('poster.jpg'),
    ])->assertSuccessful();

    $event->refresh();
    expect($event->getMedia('poster_image'))->toHaveCount(1)
        ->and($event->getFirstMediaUrl('poster_image'))->not->toBe($firstUrl);
    Storage::disk('public')->assertMissing($firstPath);
});

it('busts the ticket poster URL when replaced with the same filename through the API', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id, 'tickets_enabled' => true]);
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);

    $ticket->addMedia(UploadedFile::fake()->image('poster.jpg'))->toMediaCollection('poster');
    $firstUrl = $ticket->fresh()->getFirstMediaUrl('poster');
    $firstPath = $ticket->fresh()->getFirstMedia('poster')->getPathRelativeToRoot();

    $this->putJson("/api/events/{$event->id}/tickets/{$ticket->slug}", [
        'kind' => 'entry',
        'title' => ['en' => 'Poster Test'],
        'purchase_type' => 'first_party',
        'tmp_poster' => stageTmpUpload('poster.jpg'),
    ])->assertSuccessful();

    $ticket->refresh();
    expect($ticket->getMedia('poster'))->toHaveCount(1)
        ->and($ticket->getFirstMediaUrl('poster'))->not->toBe($firstUrl);
    Storage::disk('public')->assertMissing($firstPath);
});

it('physically deletes the event poster file when removed via the API without a replacement', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);

    $event->addMedia(UploadedFile::fake()->image('poster.jpg'))->toMediaCollection('poster_image');
    $path = $event->fresh()->getFirstMedia('poster_image')->getPathRelativeToRoot();
    Storage::disk('public')->assertExists($path);

    $this->putJson("/api/projects/{$project->username}/events/{$event->slug}", [
        'delete_poster_image' => true,
    ])->assertSuccessful();

    expect($event->fresh()->getMedia('poster_image'))->toHaveCount(0);
    Storage::disk('public')->assertMissing($path);
});

it('physically deletes the ticket poster file when removed via the API without a replacement', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id, 'tickets_enabled' => true]);
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);

    $ticket->addMedia(UploadedFile::fake()->image('poster.jpg'))->toMediaCollection('poster');
    $path = $ticket->fresh()->getFirstMedia('poster')->getPathRelativeToRoot();
    Storage::disk('public')->assertExists($path);

    $this->putJson("/api/events/{$event->id}/tickets/{$ticket->slug}", [
        'kind' => 'entry',
        'title' => ['en' => 'Poster Test'],
        'purchase_type' => 'first_party',
        'delete_poster' => true,
    ])->assertSuccessful();

    expect($ticket->fresh()->getMedia('poster'))->toHaveCount(0);
    Storage::disk('public')->assertMissing($path);
});
