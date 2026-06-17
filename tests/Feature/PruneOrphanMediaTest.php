<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create(['email_verified_at' => now()]);

    // Live media: original + conversions under users/profile_image/{id}/
    $this->media = $this->user
        ->addMedia(UploadedFile::fake()->image('profile.jpg', 600, 400))
        ->toMediaCollection('profile_image');

    $this->mediaDir = dirname($this->media->getPathRelativeToRoot());
    $this->liveFiles = Storage::disk('public')->allFiles($this->mediaDir);
});

it('keeps every file belonging to live media (sanity)', function () {
    expect($this->liveFiles)->not->toBeEmpty();
    expect(Storage::disk('public')->exists($this->media->getPathRelativeToRoot()))->toBeTrue();
});

it('reports orphans but deletes nothing on a dry run', function () {
    Storage::disk('public')->put("{$this->mediaDir}/old-orphan.jpg", 'x');
    Storage::disk('public')->put("{$this->mediaDir}/conversions/old-orphan-md.jpg", 'x');

    $this->artisan('media:prune-orphans', ['--skip-records' => true])->assertExitCode(0);

    expect(Storage::disk('public')->exists("{$this->mediaDir}/old-orphan.jpg"))->toBeTrue();
    expect(Storage::disk('public')->exists("{$this->mediaDir}/conversions/old-orphan-md.jpg"))->toBeTrue();
    foreach ($this->liveFiles as $file) {
        expect(Storage::disk('public')->exists($file))->toBeTrue();
    }
});

it('deletes orphan files but keeps live media + conversions on --force', function () {
    Storage::disk('public')->put("{$this->mediaDir}/old-orphan.jpg", 'x');
    Storage::disk('public')->put("{$this->mediaDir}/conversions/old-orphan-md.jpg", 'x');

    $this->artisan('media:prune-orphans', ['--skip-records' => true, '--force' => true])->assertExitCode(0);

    expect(Storage::disk('public')->exists("{$this->mediaDir}/old-orphan.jpg"))->toBeFalse();
    expect(Storage::disk('public')->exists("{$this->mediaDir}/conversions/old-orphan-md.jpg"))->toBeFalse();
    foreach ($this->liveFiles as $file) {
        expect(Storage::disk('public')->exists($file))->toBeTrue();
    }
});

it('never touches files outside known media roots', function () {
    Storage::disk('public')->put('unrelated/keep.jpg', 'x');
    Storage::disk('public')->put('backups/db.sql', 'x');

    $this->artisan('media:prune-orphans', ['--skip-records' => true, '--force' => true])->assertExitCode(0);

    expect(Storage::disk('public')->exists('unrelated/keep.jpg'))->toBeTrue();
    expect(Storage::disk('public')->exists('backups/db.sql'))->toBeTrue();
});

it('removes a whole orphaned sub-directory inside a live media root', function () {
    // The cover-image bug: leftover files for a model_id that no longer has any
    // live media, sitting inside an active root (users/). Must be reclaimed.
    Storage::disk('public')->put('users/profile_image/999999/old.jpg', 'x');
    Storage::disk('public')->put('users/profile_image/999999/conversions/old-md.jpg', 'x');

    $this->artisan('media:prune-orphans', ['--skip-records' => true, '--force' => true])->assertExitCode(0);

    expect(Storage::disk('public')->exists('users/profile_image/999999/old.jpg'))->toBeFalse();
    expect(Storage::disk('public')->exists('users/profile_image/999999/conversions/old-md.jpg'))->toBeFalse();
    foreach ($this->liveFiles as $file) {
        expect(Storage::disk('public')->exists($file))->toBeTrue();
    }
});

it('is idempotent — a second --force run finds nothing new', function () {
    Storage::disk('public')->put("{$this->mediaDir}/old-orphan.jpg", 'x');

    $this->artisan('media:prune-orphans', ['--skip-records' => true, '--force' => true])->assertExitCode(0);
    $this->artisan('media:prune-orphans', ['--skip-records' => true, '--force' => true])->assertExitCode(0);

    foreach ($this->liveFiles as $file) {
        expect(Storage::disk('public')->exists($file))->toBeTrue();
    }
    expect(Storage::disk('public')->exists("{$this->mediaDir}/old-orphan.jpg"))->toBeFalse();
});

it('refuses to delete from a remote disk outside production (DB/disk mismatch guard)', function () {
    // Simulate pointing at a production cloud bucket from a non-production env.
    config()->set('filesystems.disks.r2test', ['driver' => 's3', 'root' => '']);
    config()->set('media-library.disk_name', 'r2test');
    Storage::fake('r2test');

    // A real production file the (test) database knows nothing about.
    Storage::disk('r2test')->put('posts/featured_image/1/real-prod-file.jpg', 'x');

    $this->artisan('media:prune-orphans', ['--force' => true])
        ->expectsOutputToContain('Refusing to delete from remote disk')
        ->assertExitCode(1);

    expect(Storage::disk('r2test')->exists('posts/featured_image/1/real-prod-file.jpg'))->toBeTrue();
});

it('still allows a read-only dry run against a remote disk', function () {
    config()->set('filesystems.disks.r2test', ['driver' => 's3', 'root' => '']);
    config()->set('media-library.disk_name', 'r2test');
    Storage::fake('r2test');
    Storage::disk('r2test')->put('posts/featured_image/1/real-prod-file.jpg', 'x');

    $this->artisan('media:prune-orphans', ['--skip-records' => true])->assertExitCode(0);

    expect(Storage::disk('r2test')->exists('posts/featured_image/1/real-prod-file.jpg'))->toBeTrue();
});

it('keeps conversions of every photo sharing one gallery folder during a full prune (phase A regression)', function () {
    // Regression for the wiped-gallery-thumbnails bug. The custom
    // CollectionBasedPathGenerator stores every gallery photo of an Event in ONE
    // shared conversions folder: events/gallery/{event_id}/conversions/. Spatie's
    // deprecated-conversion cleanup (media-library:clean, run by Phase A) lists
    // that whole folder per media and deletes any file not matching the current
    // media's filename stem — so each photo's pass would delete its siblings'
    // thumbnails, emptying the folder. Phase A must pass --skip-conversions.
    $event = Event::factory()->create();

    $photos = collect(['photo-a', 'photo-b', 'photo-c'])->map(
        fn (string $name) => $event
            ->addMedia(UploadedFile::fake()->image("{$name}.jpg", 1200, 800))
            ->toMediaCollection('gallery')
    );

    // Sanity: lqip + sm are nonQueued, so they exist right after upload — and
    // all three photos land in the same shared conversions directory.
    $photos->each(function (Media $photo) {
        expect($photo->hasGeneratedConversion('sm'))->toBeTrue();
        expect(Storage::disk('public')->exists($photo->getPathRelativeToRoot('sm')))->toBeTrue();
        expect(Storage::disk('public')->exists($photo->getPathRelativeToRoot('lqip')))->toBeTrue();
    });
    expect($photos->map(fn (Media $p) => dirname($p->getPathRelativeToRoot('sm')))->unique())
        ->toHaveCount(1);

    // Full prune, including Phase A (media-library:clean). This is where the bug
    // emptied the shared folder.
    $this->artisan('media:prune-orphans', ['--force' => true])->assertExitCode(0);

    $photos->each(function (Media $photo) {
        expect(Storage::disk('public')->exists($photo->getPathRelativeToRoot('sm')))
            ->toBeTrue("sm conversion for {$photo->file_name} must survive prune");
        expect(Storage::disk('public')->exists($photo->getPathRelativeToRoot('lqip')))
            ->toBeTrue("lqip conversion for {$photo->file_name} must survive prune");
        expect(Storage::disk('public')->exists($photo->getPathRelativeToRoot()))->toBeTrue();
    });
});

it('removes orphaned media records whose model is gone (phase A)', function () {
    $ghost = User::factory()->create(['email_verified_at' => now()]);
    $ghostMedia = $ghost->addMedia(UploadedFile::fake()->image('ghost.jpg', 300, 300))
        ->toMediaCollection('profile_image');
    $ghostOriginal = $ghostMedia->getPathRelativeToRoot();

    // Hard-delete the row directly — bypasses Eloquent events, leaving the media record orphaned.
    User::query()->whereKey($ghost->id)->forceDelete();

    $this->artisan('media:prune-orphans', ['--force' => true])->assertExitCode(0);

    expect(Media::query()->whereKey($ghostMedia->id)->exists())->toBeFalse();
    expect(Storage::disk('public')->exists($ghostOriginal))->toBeFalse();
    // The surviving user's live media is untouched.
    expect(Storage::disk('public')->exists($this->media->getPathRelativeToRoot()))->toBeTrue();
});
