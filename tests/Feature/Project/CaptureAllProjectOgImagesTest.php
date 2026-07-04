<?php

use App\Jobs\CaptureAllProjectOgImages;
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

function giveAcmeWebsite(Project $project): void
{
    $project->links()->create([
        'label' => 'Website',
        'url' => 'https://acme.test',
        'order' => 0,
        'is_active' => true,
    ]);
}

test('capture-all dispatches the batch job with every page key', function () {
    Queue::fake();
    giveAcmeWebsite($this->project);

    $response = $this->postJson('/api/projects/acme/og-images/capture-all')
        ->assertSuccessful();

    $jobId = $response->json('job_id');
    expect(Cache::get("job:{$jobId}")['status'])->toBe('pending');
    expect(Cache::get("job:{$jobId}")['total'])->toBe(count(OgPages::KEYS));

    Queue::assertPushedOn('pdf-batch', CaptureAllProjectOgImages::class, function (CaptureAllProjectOgImages $job) use ($jobId) {
        return $job->jobId === $jobId
            && $job->remainingKeys === OgPages::KEYS
            && $job->totalKeys === count(OgPages::KEYS);
    });
});

test('capture-all returns 422 when the project has no website link', function () {
    $this->postJson('/api/projects/acme/og-images/capture-all')
        ->assertUnprocessable();
});

test('the chained job captures every page and completes the shared progress', function () {
    giveAcmeWebsite($this->project);

    $fake = app(OgScreenshotService::class);
    $total = count(OgPages::KEYS);

    // The sync queue runs each self-dispatched link immediately, so one
    // handle() call drains the whole chain.
    (new CaptureAllProjectOgImages('job-all-1', $this->project->id, OgPages::KEYS, $total))->handle($fake);

    expect($fake->capturedUrls)->toHaveCount($total);
    expect($fake->capturedUrls[0])->toBe('https://acme.test/');
    expect($fake->capturedUrls[1])->toBe('https://acme.test/brands');

    $project = $this->project->fresh();
    foreach (OgPages::KEYS as $key) {
        expect($project->getFirstMedia(OgPages::collectionFor($key)))->not->toBeNull();
    }

    $progress = Cache::get('job:job-all-1');
    expect($progress['status'])->toBe('completed');
    expect($progress['failed_keys'])->toBe([]);
    expect($progress['message'])->toContain("All {$total} pages captured");
});

test('a failing page is skipped and reported without aborting the batch', function () {
    giveAcmeWebsite($this->project);

    $fake = new class extends FakeOgScreenshotService
    {
        public function captureUrl(string $url, string $outputPath): void
        {
            if (str_ends_with($url, '/brands')) {
                throw new RuntimeException('Chrome crashed');
            }

            parent::captureUrl($url, $outputPath);
        }
    };
    app()->instance(OgScreenshotService::class, $fake);

    $total = count(OgPages::KEYS);

    (new CaptureAllProjectOgImages('job-all-2', $this->project->id, OgPages::KEYS, $total))->handle($fake);

    $project = $this->project->fresh();
    expect($project->getFirstMedia(OgPages::collectionFor('brands')))->toBeNull();
    expect($project->getFirstMedia(OgPages::collectionFor('home')))->not->toBeNull();
    expect($project->getFirstMedia(OgPages::collectionFor('rundown')))->not->toBeNull();

    $progress = Cache::get('job:job-all-2');
    expect($progress['status'])->toBe('completed');
    expect($progress['failed_keys'])->toBe(['brands']);
    expect($progress['message'])->toContain('1 failed');
});

test('the job fails progress when the website link is missing', function () {
    (new CaptureAllProjectOgImages('job-all-3', $this->project->id, OgPages::KEYS, count(OgPages::KEYS)))
        ->handle(app(OgScreenshotService::class));

    expect(Cache::get('job:job-all-3')['status'])->toBe('failed');
});

test('a second capture-all is rejected while a batch is still running', function () {
    Queue::fake();
    giveAcmeWebsite($this->project);

    $this->postJson('/api/projects/acme/og-images/capture-all')->assertSuccessful();

    $this->postJson('/api/projects/acme/og-images/capture-all')->assertStatus(409);
});

test('a finished batch releases the lock so a new one can start', function () {
    giveAcmeWebsite($this->project);

    // Sync queue: the whole chain (including finish()) runs inside this request.
    $this->postJson('/api/projects/acme/og-images/capture-all')->assertSuccessful();

    expect(Cache::get("og-capture-all:{$this->project->id}"))->toBeNull();

    $this->postJson('/api/projects/acme/og-images/capture-all')->assertSuccessful();
});

test('a failed batch releases the lock', function () {
    Cache::put("og-capture-all:{$this->project->id}", 'job-all-4', now()->addMinutes(30));

    $job = new CaptureAllProjectOgImages('job-all-4', $this->project->id, OgPages::KEYS, count(OgPages::KEYS));
    $job->failed(new RuntimeException('Worker died'));

    expect(Cache::get("og-capture-all:{$this->project->id}"))->toBeNull();
    expect(Cache::get('job:job-all-4')['status'])->toBe('failed');
});

test('a stale lock whose progress is no longer active does not block new batches', function () {
    Queue::fake();
    giveAcmeWebsite($this->project);

    // Lock left behind pointing at a completed job (e.g. cache eviction edge).
    Cache::put("og-capture-all:{$this->project->id}", 'job-old', now()->addMinutes(30));
    Cache::put('job:job-old', ['status' => 'completed'], now()->addMinutes(30));

    $this->postJson('/api/projects/acme/og-images/capture-all')->assertSuccessful();
});
