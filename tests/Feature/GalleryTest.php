<?php

use App\Jobs\BulkDeleteMedia;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $permissions = [
        'events.read', 'events.update', 'events.create', 'events.delete',
        'projects.read', 'admin.media',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/gallery";

    $this->addPhoto = fn (Event $event, string $name = 'p.jpg') => $event
        ->addMedia(UploadedFile::fake()->image($name, 800, 600))
        ->toMediaCollection('gallery');
});

it('lists the event gallery', function () {
    ($this->addPhoto)($this->event, 'a.jpg');
    ($this->addPhoto)($this->event, 'b.jpg');

    $this->getJson($this->apiBase)
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('uploads images from temp folders into the gallery', function () {
    Storage::disk('local')->put('tmp/uploads/tmp-abc/x.jpg', UploadedFile::fake()->image('x.jpg', 800, 600)->getContent());
    Storage::disk('local')->put('tmp/uploads/tmp-abc/metadata.json', json_encode(['original_name' => 'x.jpg']));

    $this->postJson($this->apiBase, ['files' => ['tmp-abc']])
        ->assertCreated()
        ->assertJsonPath('added_count', 1)
        ->assertJsonCount(1, 'data');

    expect($this->event->fresh()->getMedia('gallery'))->toHaveCount(1);
    expect(Storage::disk('local')->exists('tmp/uploads/tmp-abc'))->toBeFalse();
});

it('attaches multiple temp folders in a single request', function () {
    $folders = [];
    foreach (['a', 'b', 'c', 'd'] as $name) {
        $folder = "tmp-{$name}";
        Storage::disk('local')->put("tmp/uploads/{$folder}/{$name}.jpg", UploadedFile::fake()->image("{$name}.jpg", 800, 600)->getContent());
        Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode(['original_name' => "{$name}.jpg"]));
        $folders[] = $folder;
    }

    $this->postJson($this->apiBase, ['files' => $folders])
        ->assertCreated()
        ->assertJsonPath('added_count', 4)
        ->assertJsonPath('failed_count', 0)
        ->assertJsonCount(4, 'data');

    expect($this->event->fresh()->getMedia('gallery'))->toHaveCount(4);
    foreach ($folders as $folder) {
        expect(Storage::disk('local')->exists("tmp/uploads/{$folder}"))->toBeFalse();
    }
});

it('skips invalid temp folders without failing the whole batch', function () {
    Storage::disk('local')->put('tmp/uploads/tmp-ok/ok.jpg', UploadedFile::fake()->image('ok.jpg', 800, 600)->getContent());
    Storage::disk('local')->put('tmp/uploads/tmp-ok/metadata.json', json_encode(['original_name' => 'ok.jpg']));

    $this->postJson($this->apiBase, ['files' => ['tmp-ok', 'tmp-missing']])
        ->assertCreated()
        ->assertJsonPath('added_count', 1)
        ->assertJsonPath('failed_count', 1)
        ->assertJsonCount(1, 'data');

    expect($this->event->fresh()->getMedia('gallery'))->toHaveCount(1);
});

it('forbids upload without events.update permission', function () {
    $this->user->removeRole('master');
    $this->user->syncPermissions(['events.read']);

    Storage::disk('local')->put('tmp/uploads/tmp-x/x.jpg', UploadedFile::fake()->image('x.jpg')->getContent());
    Storage::disk('local')->put('tmp/uploads/tmp-x/metadata.json', json_encode(['original_name' => 'x.jpg']));

    $this->postJson($this->apiBase, ['files' => ['tmp-x']])->assertForbidden();
});

it('reorders gallery media via the generic media endpoint', function () {
    $a = ($this->addPhoto)($this->event, 'a.jpg');
    $b = ($this->addPhoto)($this->event, 'b.jpg');

    $this->postJson('/api/media/reorder', ['media_ids' => [$b->id, $a->id]])
        ->assertSuccessful();

    expect($b->fresh()->order_column)->toBeLessThan($a->fresh()->order_column);
});

it('bulk-deletes gallery media via the generic media endpoint', function () {
    $a = ($this->addPhoto)($this->event, 'a.jpg');
    ($this->addPhoto)($this->event, 'b.jpg');

    $this->deleteJson('/api/media/bulk-delete', ['media_ids' => [$a->id]])
        ->assertSuccessful();

    expect($this->event->fresh()->getMedia('gallery'))->toHaveCount(1);
});

it('stores duplicate-named uploads under unique disk file names', function () {
    foreach (['tmp-one', 'tmp-two'] as $folder) {
        Storage::disk('local')->put("tmp/uploads/{$folder}/photo.jpg", UploadedFile::fake()->image('photo.jpg', 800, 600)->getContent());
        Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode(['original_name' => 'photo.jpg']));
    }

    $this->postJson($this->apiBase, ['files' => ['tmp-one', 'tmp-two']])
        ->assertCreated()
        ->assertJsonPath('added_count', 2);

    $media = $this->event->fresh()->getMedia('gallery');
    expect($media)->toHaveCount(2);
    expect($media->pluck('file_name')->unique())->toHaveCount(2);
});

it('updates a gallery photo caption and exposes it in the resource', function () {
    $media = ($this->addPhoto)($this->event, 'a.jpg');

    $this->patchJson("/api/media/{$media->id}", ['caption' => 'Opening ceremony'])
        ->assertSuccessful()
        ->assertJsonPath('data.caption', 'Opening ceremony');

    expect($media->fresh()->getCustomProperty('caption'))->toBe('Opening ceremony');

    $this->getJson($this->apiBase)->assertJsonPath('data.0.caption', 'Opening ceremony');
});

it('clears a gallery photo caption when given an empty string', function () {
    $media = ($this->addPhoto)($this->event, 'a.jpg');
    $media->setCustomProperty('caption', 'old')->save();

    $this->patchJson("/api/media/{$media->id}", ['caption' => ''])
        ->assertSuccessful()
        ->assertJsonPath('data.caption', null);

    expect($this->event->fresh()->getMedia('gallery')->first()->getCustomProperty('caption'))->toBeNull();
});

it('forbids caption update without manage permission', function () {
    $media = ($this->addPhoto)($this->event, 'a.jpg');
    $this->user->removeRole('master');
    $this->user->syncPermissions(['events.read']);

    $this->patchJson("/api/media/{$media->id}", ['caption' => 'nope'])->assertForbidden();
});

it('saves the gallery aspect ratio and merges into existing settings', function () {
    $this->event->update(['settings' => ['tax_rate' => 11]]);

    $this->patchJson("{$this->apiBase}/settings", ['aspect_ratio' => '4:5'])
        ->assertSuccessful()
        ->assertJsonPath('data.aspect_ratio', '4:5');

    $settings = $this->event->fresh()->settings;
    expect($settings['gallery_aspect_ratio'])->toBe('4:5');
    expect($settings['tax_rate'])->toBe(11);
});

it('accepts a custom aspect ratio', function () {
    $this->patchJson("{$this->apiBase}/settings", ['aspect_ratio' => '21:9'])
        ->assertSuccessful();

    expect($this->event->fresh()->settings['gallery_aspect_ratio'])->toBe('21:9');
});

it('rejects an invalid aspect ratio', function (string $ratio) {
    $this->patchJson("{$this->apiBase}/settings", ['aspect_ratio' => $ratio])
        ->assertStatus(422);
})->with(['0:5', '4:0', 'abc', '4-5', '4:', ':5', '1000:9']);

it('forbids aspect ratio update without events.update permission', function () {
    $this->user->removeRole('master');
    $this->user->syncPermissions(['events.read']);

    $this->patchJson("{$this->apiBase}/settings", ['aspect_ratio' => '1:1'])->assertForbidden();
});

it('queues a bulk delete and returns a job id scoped to the event gallery', function () {
    Queue::fake();

    $a = ($this->addPhoto)($this->event, 'a.jpg');
    $b = ($this->addPhoto)($this->event, 'b.jpg');

    $other = Event::factory()->create(['project_id' => $this->project->id]);
    $foreign = ($this->addPhoto)($other, 'c.jpg');

    $response = $this->postJson("{$this->apiBase}/bulk-delete", [
        'media_ids' => [$a->id, $b->id, $foreign->id],
    ])->assertSuccessful();

    $jobId = $response->json('job_id');
    expect($jobId)->not->toBeNull();
    expect(Cache::get("job:{$jobId}")['total'])->toBe(2); // foreign id dropped

    Queue::assertPushed(BulkDeleteMedia::class, function (BulkDeleteMedia $job) use ($a, $b, $jobId) {
        return $job->jobId === $jobId
            && $job->mediaIds === [$a->id, $b->id]
            && $job->responseCacheTags === ['gallery'];
    });
});

it('rejects a bulk delete with no matching gallery photos', function () {
    $other = Event::factory()->create(['project_id' => $this->project->id]);
    $foreign = ($this->addPhoto)($other, 'c.jpg');

    $this->postJson("{$this->apiBase}/bulk-delete", ['media_ids' => [$foreign->id]])
        ->assertStatus(422);
});

it('forbids bulk delete without events.update permission', function () {
    $media = ($this->addPhoto)($this->event, 'a.jpg');
    $this->user->removeRole('master');
    $this->user->syncPermissions(['events.read']);

    $this->postJson("{$this->apiBase}/bulk-delete", ['media_ids' => [$media->id]])->assertForbidden();
});

it('deletes media and reports progress when the job runs', function () {
    $a = ($this->addPhoto)($this->event, 'a.jpg');
    $b = ($this->addPhoto)($this->event, 'b.jpg');

    $jobId = 'test-job-id';
    Cache::put("job:{$jobId}", ['status' => 'pending', 'total' => 2, 'processed' => 0], now()->addMinutes(30));

    (new BulkDeleteMedia($jobId, [$a->id, $b->id], $this->user->id, ['gallery']))->handle();

    expect($this->event->fresh()->getMedia('gallery'))->toHaveCount(0);

    $progress = Cache::get("job:{$jobId}");
    expect($progress['status'])->toBe('completed');
    expect($progress['percentage'])->toBe(100);
    expect($progress['deleted_count'])->toBe(2);
    expect($progress['deleted_ids'])->toBe([$a->id, $b->id]);
});
