<?php

use App\Jobs\CaptureProjectOgImage;
use App\Models\Project;
use App\Models\User;
use App\Services\Og\OgScreenshotService;
use App\Support\OgPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeOgScreenshotService;

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

function giveProjectWebsite(Project $project, string $url = 'https://acme.test'): void
{
    $project->links()->create([
        'label' => 'Website',
        'url' => $url,
        'order' => 0,
        'is_active' => true,
    ]);
}

test('capture dispatches the job on the pdf-batch queue and pre-seeds progress', function () {
    Queue::fake();
    giveProjectWebsite($this->project);

    $response = $this->postJson('/api/projects/acme/og-images/brands/capture')
        ->assertSuccessful();

    $jobId = $response->json('job_id');
    expect($jobId)->not->toBeEmpty();
    expect(Cache::get("job:{$jobId}")['status'])->toBe('pending');

    Queue::assertPushedOn('pdf-batch', CaptureProjectOgImage::class, function (CaptureProjectOgImage $job) use ($jobId) {
        return $job->jobId === $jobId
            && $job->projectId === $this->project->id
            && $job->pageKey === 'brands';
    });
});

test('capture returns 422 when the project has no website link', function () {
    $this->postJson('/api/projects/acme/og-images/home/capture')
        ->assertUnprocessable();
});

test('capture returns 404 for an unknown page key', function () {
    giveProjectWebsite($this->project);

    $this->postJson('/api/projects/acme/og-images/not-a-page/capture')
        ->assertNotFound();
});

test('the job screenshots the page url and stores the OG image', function () {
    giveProjectWebsite($this->project);

    $fake = app(OgScreenshotService::class);

    (new CaptureProjectOgImage('job-test-1', $this->project->id, 'brands'))->handle($fake);

    expect($fake->capturedUrls)->toBe(['https://acme.test/brands']);

    $media = $this->project->fresh()->getFirstMedia(OgPages::collectionFor('brands'));
    expect($media)->not->toBeNull();
    expect($media->file_name)->toContain('og-brands');
    expect($media->getCustomProperty('source'))->toBe('capture');

    $progress = Cache::get('job:job-test-1');
    expect($progress['status'])->toBe('completed');
    expect($progress['image']['url'])->toBe($media->getUrl());
});

test('the home key captures the website root', function () {
    giveProjectWebsite($this->project, 'https://acme.test/');

    $fake = app(OgScreenshotService::class);

    (new CaptureProjectOgImage('job-test-2', $this->project->id, 'home'))->handle($fake);

    expect($fake->capturedUrls)->toBe(['https://acme.test/']);
});

test('the job fails progress when the website link disappeared', function () {
    (new CaptureProjectOgImage('job-test-3', $this->project->id, 'home'))->handle(app(OgScreenshotService::class));

    $progress = Cache::get('job:job-test-3');
    expect($progress['status'])->toBe('failed');
    expect($progress['error_message'])->toContain('Website');
});

test('failed() marks the progress cache as failed', function () {
    $job = new CaptureProjectOgImage('job-test-4', $this->project->id, 'home');
    $job->failed(new RuntimeException('Chrome crashed'));

    expect(Cache::get('job:job-test-4')['status'])->toBe('failed');
    expect(Cache::get('job:job-test-4')['error_message'])->toBe('Chrome crashed');
});

test('the fake screenshot service is bound as a singleton for assertions', function () {
    expect(app(OgScreenshotService::class))->toBeInstanceOf(FakeOgScreenshotService::class);
    expect(app(OgScreenshotService::class))->toBe(app(OgScreenshotService::class));
});
