<?php

use App\Http\Middleware\PublicApiCorsScope;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Project;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    // A deterministic baseline for the credentialed default. Each test issues
    // exactly ONE request, because PublicApiCorsScope narrows this config in
    // place and the container is reused between calls inside a single test.
    // Two entries on purpose. php-cors short-circuits a single-entry allowlist
    // by setting that one origin on every response regardless of the request's
    // Origin, which would make the "unlisted origin" case below untestable.
    config([
        'cors.allowed_origins' => ['https://pmone.id', 'http://localhost:3000'],
        'cors.allowed_origins_patterns' => [],
        'cors.supports_credentials' => true,
    ]);
});

// ─── CORS on the browser-facing reads ────────────────────────────────────────

it('answers a public read with a wildcard origin', function () {
    $response = $this->withHeaders(['Origin' => 'https://megabuild.co.id'])
        ->getJson('/api/public/blog/posts');

    $response->assertOk();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

it('sets the wildcard even when the request carries no Origin', function () {
    // This is the request shape that populates the caches — the prerenderer,
    // curl, a crawler. If the header were absent here, the stored copy would be
    // unusable by every browser that later got served it from the edge.
    $response = $this->getJson('/api/public/blog/posts');

    $response->assertOk();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

it('does not vary a public read on Origin', function () {
    // Cloudflare honours no Vary but Accept-Encoding. A Vary: Origin here would
    // be silently ignored at the edge, and one site's cached copy would be
    // replayed to the other fifteen.
    $response = $this->withHeaders(['Origin' => 'https://megabuild.co.id'])
        ->getJson('/api/public/blog/posts');

    expect((string) $response->headers->get('Vary'))->not->toContain('Origin');
});

it('does not allow credentials on a public read', function () {
    $response = $this->withHeaders(['Origin' => 'https://megabuild.co.id'])
        ->getJson('/api/public/blog/posts');

    expect($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
});

it('leaves the credentialed per-origin config alone for keyed routes', function () {
    // The Form Builder embeds sit under the same prefix but keep a mandatory
    // key, so they must keep the strict treatment.
    $response = $this->withHeaders(['Origin' => 'https://pmone.id'])
        ->getJson('/api/public/projects/nobody/forms/nothing');

    $response->assertUnauthorized();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://pmone.id');
});

it('rejects an unlisted origin on a keyed route', function () {
    $response = $this->withHeaders(['Origin' => 'https://megabuild.co.id'])
        ->getJson('/api/public/projects/nobody/forms/nothing');

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

it('answers a tracking beacon with a wildcard origin', function () {
    $project = Project::factory()->create();

    $response = $this->withHeaders(['Origin' => 'https://megabuild.co.id'])
        ->postJson('/api/track/visit', [
            'visitable_type' => Project::class,
            'visitable_id' => $project->id,
        ]);

    $response->assertCreated();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

// ─── The CORS allowlist must not drift from the route table ──────────────────

it('widens CORS for exactly the optional-key reads and nothing else', function () {
    $mismatches = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/public/')) {
            continue;
        }
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $isOptional = in_array('api.key:optional', $route->gatherMiddleware(), true);
        $uri = preg_replace('/\{[^}]+\}/', 'x', $route->uri());
        $isWidened = PublicApiCorsScope::isBrowserRead(Request::create('/'.$uri, 'GET'));

        if ($isOptional !== $isWidened) {
            $mismatches[] = sprintf(
                '%s (api.key:optional=%s, CORS widened=%s)',
                $route->uri(),
                $isOptional ? 'yes' : 'no',
                $isWidened ? 'yes' : 'no',
            );
        }
    }

    expect($mismatches)->toBe([], "PublicApiCorsScope::READ_PATHS has drifted from routes/api.php:\n  ".implode("\n  ", $mismatches));
});

// ─── `active` as an event slug ───────────────────────────────────────────────

it('resolves the active event for every section endpoint', function (string $section) {
    $project = Project::factory()->create();
    Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);

    $this->getJson("/api/public/projects/{$project->username}/events/active/{$section}")
        ->assertOk();
})->with(['faqs', 'gallery', 'programs', 'rundown', 'partners', 'media-coverages', 'guests', 'brands']);

it('returns the same payload for the active alias as for the real slug', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);

    $viaAlias = $this->getJson("/api/public/projects/{$project->username}/events/active/faqs");
    ResponseCache::clear();
    $viaSlug = $this->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/faqs");

    expect($viaAlias->json())->toBe($viaSlug->json());
});

it('falls back to the latest published edition when none is active', function () {
    $project = Project::factory()->create();
    $older = Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => false,
        'edition_number' => 3,
    ]);
    $latest = Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => false,
        'edition_number' => 9,
    ]);

    foreach ([[$older, 'OLDER'], [$latest, 'LATEST']] as [$event, $label]) {
        Faq::factory()->create([
            'event_id' => $event->id,
            'question' => ['en' => $label, 'id' => $label],
            'answer' => ['en' => '<p>x</p>', 'id' => '<p>x</p>'],
            'order_column' => 1,
        ]);
    }

    $this->getJson("/api/public/projects/{$project->username}/events/active")
        ->assertNotFound();

    // The section endpoints are the ones that matter: unlike /events/active,
    // they fall back rather than 404, which is what keeps a project between
    // editions rendering its previous edition's content.
    $this->getJson("/api/public/projects/{$project->username}/events/active/faqs?locale=en")
        ->assertOk()
        ->assertJsonPath('data.0.q', 'LATEST');
});

// ─── Beacon payload ──────────────────────────────────────────────────────────

it('records page_url as the referer', function () {
    $project = Project::factory()->create();

    $this->postJson('/api/track/visit', [
        'visitable_type' => Project::class,
        'visitable_id' => $project->id,
        'page_url' => 'https://megabuild.co.id/news/some-article',
    ])->assertCreated();

    expect(Visit::sole()->referer)->toBe('https://megabuild.co.id/news/some-article');
});

it('falls back to the Referer header when no page_url is sent', function () {
    $project = Project::factory()->create();

    $this->withHeaders(['Referer' => 'https://pmone.id/posts/x'])
        ->postJson('/api/track/visit', [
            'visitable_type' => Project::class,
            'visitable_id' => $project->id,
        ])->assertCreated();

    expect(Visit::sole()->referer)->toBe('https://pmone.id/posts/x');
});

it('rejects a page_url that is not an http url', function () {
    $project = Project::factory()->create();

    $this->postJson('/api/track/visit', [
        'visitable_type' => Project::class,
        'visitable_id' => $project->id,
        'page_url' => 'javascript:alert(1)',
    ])->assertJsonValidationErrorFor('page_url');
});

it('accepts a form-urlencoded beacon body', function () {
    // What navigator.sendBeacon(url, new URLSearchParams(...)) actually sends.
    // A CORS-safelisted Content-Type is what keeps the request simple, so it
    // needs no preflight and survives page unload.
    $project = Project::factory()->create();

    $this->call(
        'POST',
        '/api/track/visit',
        [
            'visitable_type' => Project::class,
            'visitable_id' => $project->id,
            'page_url' => 'https://megabuild.co.id/',
        ],
        server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
    )->assertCreated();

    expect(Visit::sole()->referer)->toBe('https://megabuild.co.id/');
});

it('404s the active alias when the project has no published event', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/public/projects/{$project->username}/events/active/faqs")
        ->assertNotFound();
});
