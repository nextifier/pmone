<?php

use App\Exports\RundownItemsExport;
use App\Imports\RundownItemsImport;
use App\Jobs\ProcessExcelImport;
use App\Models\Event;
use App\Models\Project;
use App\Models\RundownItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'rundown_items.create', 'rundown_items.read', 'rundown_items.update',
        'rundown_items.delete', 'rundown_items.restore',
        'events.read',
    ];

    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    Storage::fake('local');

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/rundown-items";
});

function makeRundownItem(int $eventId, array $attributes = [], array $categories = ['keynote', 'opening']): RundownItem
{
    $item = RundownItem::factory()->create(array_merge([
        'event_id' => $eventId,
        'date' => '2026-07-22',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'title' => ['en' => 'Welcome Keynote', 'id' => 'Pidato Pembukaan'],
        'subtitle' => ['en' => 'Opening session', 'id' => 'Sesi pembukaan'],
        'description' => ['en' => 'Welcoming address.', 'id' => 'Sambutan pembuka.'],
        'theme' => ['en' => 'Innovation', 'id' => 'Inovasi'],
        'location' => ['en' => 'Main Hall', 'id' => 'Aula Utama'],
        'presented_by' => ['en' => 'Acme Corp', 'id' => 'Acme Corp'],
        'moderator' => ['en' => 'Jane Roe', 'id' => 'Jane Roe'],
        'speakers' => [['name' => 'John Doe', 'title' => 'Founder', 'organization' => 'Acme']],
        'panelists' => [['name' => 'Mary Smith', 'title' => 'CTO']],
        'is_active' => true,
    ], $attributes));

    $item->syncTagsWithType($categories, 'rundown_category');

    return $item->fresh(['tags']);
}

it('exports rundown items as xlsx with translatable columns flattened', function () {
    Excel::fake();

    makeRundownItem($this->event->id);

    $response = $this->get("{$this->apiBase}/export");

    $response->assertOk();

    Excel::assertDownloaded(
        "rundown_{$this->event->slug}_".now()->format('Y-m-d_His').'.xlsx',
        function (RundownItemsExport $export) {
            $rows = $export->collection();
            $first = $export->map($rows->first());

            expect($export->headings())->toContain('Title (EN)', 'Title (ID)', 'Speakers (JSON)', 'Categories');
            expect($first[3])->toBe('Welcome Keynote');
            expect($first[4])->toBe('Pidato Pembukaan');

            return true;
        }
    );
});

it('exports rundown items as json preserving translation structure', function () {
    makeRundownItem($this->event->id);

    $response = $this->get("{$this->apiBase}/export/json");

    $response->assertOk();

    $payload = json_decode($response->streamedContent(), true);

    expect($payload)->toHaveKeys(['exported_at', 'event', 'items'])
        ->and($payload['event']['slug'])->toBe($this->event->slug)
        ->and($payload['items'])->toHaveCount(1);

    $item = $payload['items'][0];
    expect($item['title'])->toBe(['en' => 'Welcome Keynote', 'id' => 'Pidato Pembukaan'])
        ->and($item['speakers'])->toBeArray()
        ->and($item['speakers'][0]['name'])->toBe('John Doe')
        ->and($item['categories'])->toContain('keynote')
        ->and($item['is_active'])->toBeTrue();
});

it('imports rundown items from json and appends to existing items', function () {
    $existing = makeRundownItem($this->event->id, ['title' => ['en' => 'Existing Item']]);

    $response = $this->postJson("{$this->apiBase}/import/json", [
        'event' => ['slug' => $this->event->slug],
        'items' => [
            [
                'date' => '2026-07-22',
                'start_time' => '11:00',
                'end_time' => '12:00',
                'title' => ['en' => 'Imported Item', 'id' => 'Item Diimpor'],
                'subtitle' => ['en' => '', 'id' => ''],
                'description' => ['en' => 'Imported description.'],
                'theme' => [],
                'location' => ['en' => 'Aula B', 'id' => 'Aula B'],
                'presented_by' => [],
                'moderator' => [],
                'panelists' => [['name' => 'Panelist A', 'title' => 'Director']],
                'speakers' => [['name' => 'Speaker A', 'title' => 'CEO', 'organization' => 'Acme']],
                'categories' => ['workshop'],
                'is_active' => false,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('imported_count', 1)
        ->assertJsonPath('errors', []);

    expect(RundownItem::where('event_id', $this->event->id)->count())->toBe(2);
    expect(RundownItem::find($existing->id))->not->toBeNull();

    $imported = RundownItem::where('event_id', $this->event->id)
        ->where('id', '!=', $existing->id)
        ->first();

    expect($imported->getTranslation('title', 'en'))->toBe('Imported Item')
        ->and($imported->getTranslation('title', 'id'))->toBe('Item Diimpor')
        ->and($imported->is_active)->toBeFalse()
        ->and($imported->panelists[0]['name'])->toBe('Panelist A')
        ->and($imported->tagsWithType('rundown_category')->pluck('name')->all())->toBe(['workshop']);
});

it('rejects json import payload that is missing the items array', function () {
    $response = $this->postJson("{$this->apiBase}/import/json", [
        'event' => ['slug' => $this->event->slug],
    ]);

    $response->assertStatus(422);
});

it('reports validation errors per row but commits the valid ones', function () {
    $response = $this->postJson("{$this->apiBase}/import/json", [
        'items' => [
            [
                'date' => '2026-07-22',
                'title' => ['id' => 'Tanpa Judul Inggris'],
            ],
            [
                'date' => '2026-07-22',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'title' => ['en' => 'Valid Item'],
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('imported_count', 1);

    $errors = $response->json('errors');
    expect($errors)->toHaveCount(1)
        ->and($errors[0]['row'])->toBe(1)
        ->and($errors[0]['errors'])->toHaveKey('title.en');

    expect(RundownItem::where('event_id', $this->event->id)->count())->toBe(1);
});

it('scopes json import strictly to the event in the route', function () {
    $otherEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $this->postJson("{$this->apiBase}/import/json", [
        'items' => [
            ['title' => ['en' => 'Should be in current event only']],
        ],
    ])->assertOk();

    expect(RundownItem::where('event_id', $this->event->id)->count())->toBe(1)
        ->and(RundownItem::where('event_id', $otherEvent->id)->count())->toBe(0);
});

it('downloads the xlsx import template', function () {
    Excel::fake();

    $response = $this->get("{$this->apiBase}/import/template");

    $response->assertOk();

    Excel::assertDownloaded('rundown_import_template.xlsx');
});

it('routes json uploads through the /import endpoint without dispatching a queue job', function () {
    Queue::fake();

    $tempFolder = 'tmp-test-json-'.uniqid();
    $payload = [
        'items' => [
            [
                'date' => '2026-07-22',
                'start_time' => '11:00',
                'end_time' => '12:00',
                'title' => ['en' => 'Imported From Endpoint', 'id' => 'Diimpor Dari Endpoint'],
                'is_active' => true,
                'categories' => ['from-endpoint'],
            ],
        ],
    ];

    Storage::disk('local')->put(
        "tmp/uploads/{$tempFolder}/rundown.json",
        json_encode($payload),
    );
    Storage::disk('local')->put(
        "tmp/uploads/{$tempFolder}/metadata.json",
        json_encode([
            'original_name' => 'rundown.json',
            'mime_type' => 'application/json',
        ]),
    );

    $response = $this->postJson("{$this->apiBase}/import", ['file' => $tempFolder]);

    $response->assertOk()->assertJsonStructure(['import_id']);

    Queue::assertNothingPushed();

    $cacheKey = 'import:'.$response->json('import_id');
    $cached = Cache::get($cacheKey);

    expect($cached['status'])->toBe('completed')
        ->and($cached['imported_count'])->toBe(1);

    expect(RundownItem::where('event_id', $this->event->id)->count())->toBe(1);

    $imported = RundownItem::where('event_id', $this->event->id)->first();
    expect($imported->getTranslation('title', 'en'))->toBe('Imported From Endpoint')
        ->and($imported->tagsWithType('rundown_category')->pluck('name')->all())->toBe(['from-endpoint']);

    expect(Storage::disk('local')->exists("tmp/uploads/{$tempFolder}"))->toBeFalse();
});

it('dispatches the queued xlsx import with the correct constructor args', function () {
    Queue::fake();

    $tempFolder = 'tmp-test-'.uniqid();
    Storage::disk('local')->put("tmp/uploads/{$tempFolder}/rundown.xlsx", 'fake-content');
    Storage::disk('local')->put("tmp/uploads/{$tempFolder}/metadata.json", json_encode([
        'original_name' => 'rundown.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]));

    $response = $this->postJson("{$this->apiBase}/import", ['file' => $tempFolder]);

    $response->assertOk()->assertJsonStructure(['import_id']);

    Queue::assertPushed(
        ProcessExcelImport::class,
        function ($job) {
            return $job->importClass === RundownItemsImport::class
                && $job->constructorArgs === [$this->event->id];
        }
    );
});

it('blocks export for users without event access', function () {
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($stranger);

    $response = $this->get("{$this->apiBase}/export");

    expect($response->status())->toBeIn([401, 403]);
});

it('round-trips xlsx export back through import without data loss', function () {
    // Build two source items covering every exported field.
    makeRundownItem($this->event->id, [
        'date' => '2026-07-22',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'title' => ['en' => 'Welcome Keynote', 'id' => 'Pidato Pembukaan'],
        'subtitle' => ['en' => 'Opening session', 'id' => 'Sesi pembukaan'],
        'description' => ['en' => 'Welcoming address.', 'id' => 'Sambutan pembuka.'],
        'theme' => ['en' => 'Innovation', 'id' => 'Inovasi'],
        'location' => ['en' => 'Main Hall', 'id' => 'Aula Utama'],
        'presented_by' => ['en' => 'Acme Corp', 'id' => 'Acme Corp'],
        'moderator' => ['en' => 'Jane Roe', 'id' => 'Jane Roe'],
        'panelists' => [['name' => 'Mary Smith', 'title' => 'CTO']],
        'speakers' => [['name' => 'John Doe', 'title' => 'Founder', 'organization' => 'Acme']],
        'is_active' => true,
    ], categories: ['keynote', 'opening']);

    makeRundownItem($this->event->id, [
        'date' => '2026-07-23',
        'start_time' => '13:30',
        'end_time' => '14:45',
        'title' => ['en' => 'Panel Discussion', 'id' => 'Diskusi Panel'],
        'subtitle' => ['en' => null, 'id' => null],
        'description' => ['en' => null, 'id' => null],
        'theme' => ['en' => 'Future of Work', 'id' => 'Masa Depan Kerja'],
        'location' => ['en' => 'Studio B', 'id' => 'Studio B'],
        'presented_by' => ['en' => null, 'id' => null],
        'moderator' => ['en' => 'Alex Tan', 'id' => 'Alex Tan'],
        'panelists' => [
            ['name' => 'Panelist One', 'title' => 'VP Eng'],
            ['name' => 'Panelist Two', 'title' => 'Director'],
        ],
        'speakers' => [],
        'is_active' => false,
    ], categories: ['panel']);

    // Persist a real XLSX produced by the export pipeline.
    $exportPath = sys_get_temp_dir().'/rundown_roundtrip_'.uniqid().'.xlsx';
    Excel::store(
        new RundownItemsExport($this->event->id),
        basename($exportPath),
        config('filesystems.default'),
    );

    // Excel::store writes through the configured disk; copy to a known location for import.
    $disk = Storage::disk(config('filesystems.default'));
    $absolute = $disk->path(basename($exportPath));

    // Import into a fresh event so we can compare without contaminating the source.
    $targetEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $import = new RundownItemsImport($targetEvent->id);
    Excel::import($import, $absolute);

    expect($import->getFailures())->toBe([])
        ->and($import->getImportedCount())->toBe(2);

    $imported = RundownItem::where('event_id', $targetEvent->id)
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    expect($imported)->toHaveCount(2);

    $first = $imported[0];
    expect($first->getTranslation('title', 'en'))->toBe('Welcome Keynote')
        ->and($first->getTranslation('title', 'id'))->toBe('Pidato Pembukaan')
        ->and($first->getTranslation('description', 'en'))->toBe('Welcoming address.')
        ->and($first->getTranslation('moderator', 'en'))->toBe('Jane Roe')
        ->and($first->date->format('Y-m-d'))->toBe('2026-07-22')
        ->and(substr($first->start_time, 0, 5))->toBe('09:00')
        ->and(substr($first->end_time, 0, 5))->toBe('10:00')
        ->and($first->is_active)->toBeTrue()
        ->and($first->panelists[0]['name'])->toBe('Mary Smith')
        ->and($first->speakers[0]['organization'])->toBe('Acme')
        ->and($first->tagsWithType('rundown_category')->pluck('name')->sort()->values()->all())
        ->toBe(['keynote', 'opening']);

    $second = $imported[1];
    expect($second->getTranslation('title', 'en'))->toBe('Panel Discussion')
        ->and($second->getTranslation('subtitle', 'en'))->toBe('')
        ->and($second->is_active)->toBeFalse()
        ->and(count($second->panelists))->toBe(2)
        ->and($second->panelists[1]['title'])->toBe('Director')
        ->and(empty($second->speakers))->toBeTrue()
        ->and($second->tagsWithType('rundown_category')->pluck('name')->all())
        ->toBe(['panel']);

    @unlink($absolute);
});

it('flattens legacy localized speakers/panelists dict on export and round-trips through import', function () {
    // Legacy data shape: {en: [...], id: [...]} dict instead of flat array.
    $item = RundownItem::factory()->create([
        'event_id' => $this->event->id,
        'date' => '2026-07-22',
        'start_time' => '08:55',
        'end_time' => '09:05',
        'title' => ['en' => 'Opening & Welcome', 'id' => 'Sambutan & Pembukaan'],
        'speakers' => [
            'en' => [['name' => 'Jeffrey Haribowo, Chairman of INCA (ASKINDO)']],
            'id' => [['name' => 'Jeffrey Haribowo, Ketua INCA (ASKINDO)']],
        ],
        'panelists' => [
            'en' => [['name' => 'Mary Smith', 'title' => 'CTO']],
            'id' => [['name' => 'Mary Smith', 'title' => 'CTO']],
        ],
        'is_active' => true,
    ]);
    $item->syncTagsWithType(['legacy'], 'rundown_category');

    // JSON export must flatten dict to flat array.
    $exportResponse = $this->get("{$this->apiBase}/export/json");
    $exportResponse->assertOk();
    $payload = json_decode($exportResponse->streamedContent(), true);

    expect($payload['items'][0]['speakers'])->toBeArray()
        ->and($payload['items'][0]['speakers'][0]['name'])->toBe('Jeffrey Haribowo, Chairman of INCA (ASKINDO)')
        ->and(array_is_list($payload['items'][0]['speakers']))->toBeTrue()
        ->and($payload['items'][0]['panelists'][0]['title'])->toBe('CTO');

    // Re-import the export into a fresh event — must succeed.
    $targetEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $targetBase = "/api/projects/{$this->project->username}/events/{$targetEvent->slug}/rundown-items";

    $importResponse = $this->postJson("{$targetBase}/import/json", $payload);
    $importResponse->assertOk()
        ->assertJsonPath('imported_count', 1)
        ->assertJsonPath('errors', []);

    $imported = RundownItem::where('event_id', $targetEvent->id)->first();
    expect($imported->getTranslation('title', 'en'))->toBe('Opening & Welcome')
        ->and($imported->speakers[0]['name'])->toBe('Jeffrey Haribowo, Chairman of INCA (ASKINDO)');
});

it('round-trips json export back through import without data loss', function () {
    makeRundownItem($this->event->id, [
        'date' => '2026-07-22',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'title' => ['en' => 'Welcome Keynote', 'id' => 'Pidato Pembukaan'],
        'subtitle' => ['en' => 'Opening session', 'id' => 'Sesi pembukaan'],
        'description' => ['en' => 'Welcoming address.', 'id' => 'Sambutan pembuka.'],
        'theme' => ['en' => 'Innovation', 'id' => 'Inovasi'],
        'location' => ['en' => 'Main Hall', 'id' => 'Aula Utama'],
        'presented_by' => ['en' => 'Acme Corp', 'id' => 'Acme Corp'],
        'moderator' => ['en' => 'Jane Roe', 'id' => 'Jane Roe'],
        'panelists' => [['name' => 'Mary Smith', 'title' => 'CTO']],
        'speakers' => [['name' => 'John Doe', 'title' => 'Founder', 'organization' => 'Acme']],
        'is_active' => true,
    ], categories: ['keynote, with comma', 'opening']);

    $exportResponse = $this->get("{$this->apiBase}/export/json");
    $exportResponse->assertOk();

    $payload = json_decode($exportResponse->streamedContent(), true);
    expect($payload['items'])->toHaveCount(1);

    $targetEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $targetBase = "/api/projects/{$this->project->username}/events/{$targetEvent->slug}/rundown-items";

    $importResponse = $this->postJson("{$targetBase}/import/json", $payload);
    $importResponse->assertOk()
        ->assertJsonPath('imported_count', 1)
        ->assertJsonPath('errors', []);

    $imported = RundownItem::where('event_id', $targetEvent->id)->first();

    expect($imported->getTranslation('title', 'en'))->toBe('Welcome Keynote')
        ->and($imported->getTranslation('title', 'id'))->toBe('Pidato Pembukaan')
        ->and($imported->getTranslation('description', 'id'))->toBe('Sambutan pembuka.')
        ->and($imported->panelists[0]['name'])->toBe('Mary Smith')
        ->and($imported->speakers[0]['organization'])->toBe('Acme')
        ->and($imported->is_active)->toBeTrue()
        ->and($imported->tagsWithType('rundown_category')->pluck('name')->sort()->values()->all())
        ->toBe(['keynote, with comma', 'opening']);
});
