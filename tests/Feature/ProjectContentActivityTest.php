<?php

use App\Models\Event;
use App\Models\Faq;
use App\Models\MediaCoverage;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectContentActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Set a row's timestamp without Eloquent putting it back to now().
 *
 * saveQuietly() still refreshes `updated_at`, so every test that cares about
 * ordering has to go straight to the table.
 */
function touchRow(string $table, int $id, string $at): void
{
    DB::table($table)->where('id', $id)->update(['updated_at' => $at]);
}

/** A project with one event, both parked far in the past so tests can be explicit. */
function contentProject(string $username = 'megabuild'): Event
{
    $project = Project::factory()->create(['username' => $username]);
    $event = Event::factory()->create(['project_id' => $project->id]);

    touchRow('projects', $project->id, '2020-01-01 00:00:00');
    touchRow('events', $event->id, '2020-01-01 00:00:00');

    return $event;
}

/** One gallery photo on an event, with the timestamp the test wants. */
function galleryPhoto(Event $event, string $at): void
{
    DB::table('media')->insert([
        'model_type' => Event::class,
        'model_id' => $event->id,
        'uuid' => (string) Str::uuid(),
        'collection_name' => 'gallery',
        'name' => 'photo',
        'file_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'size' => 1024,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

// ---------------------------------------------------------------------------
// breakdown() / lastChangedAt()
// ---------------------------------------------------------------------------

it('reports the newest change per source, newest source first', function () {
    $event = contentProject();

    $faq = Faq::factory()->create(['event_id' => $event->id]);
    $program = Program::factory()->create(['event_id' => $event->id]);

    touchRow('faqs', $faq->id, '2026-08-07 12:00:00');
    touchRow('programs', $program->id, '2026-08-07 09:00:00');
    touchRow('projects', $event->project_id, '2026-08-06 09:00:00');
    galleryPhoto($event, '2026-08-05 09:00:00');

    $sources = ProjectContentActivity::breakdown(['megabuild'])['megabuild'];

    // The event is still parked in 2020, so it sorts last.
    expect(array_keys($sources))->toBe(['faqs', 'programs', 'project', 'gallery', 'event'])
        ->and($sources['faqs']->toDateTimeString())->toBe('2026-08-07 12:00:00')
        ->and($sources['programs']->toDateTimeString())->toBe('2026-08-07 09:00:00');
});

it('still reports the overall newest change', function () {
    $event = contentProject();

    $faq = Faq::factory()->create(['event_id' => $event->id]);
    touchRow('faqs', $faq->id, '2026-08-07 12:00:00');
    touchRow('projects', $event->project_id, '2026-08-06 09:00:00');

    $last = ProjectContentActivity::lastChangedAt(['megabuild']);

    expect($last['megabuild']->toDateTimeString())->toBe('2026-08-07 12:00:00')
        ->and($last['megabuild'])->toEqual(max(ProjectContentActivity::breakdown(['megabuild'])['megabuild']));
});

it('keeps projects apart', function () {
    $megabuild = contentProject('megabuild');
    $icc = contentProject('icc');

    touchRow('projects', $megabuild->project_id, '2026-08-07 12:00:00');
    touchRow('projects', $icc->project_id, '2026-08-01 12:00:00');

    $last = ProjectContentActivity::lastChangedAt(['megabuild', 'icc']);

    expect($last['megabuild']->toDateTimeString())->toBe('2026-08-07 12:00:00')
        ->and($last['icc']->toDateTimeString())->toBe('2026-08-01 12:00:00');
});

it('returns nothing for an empty username list', function () {
    contentProject();

    expect(ProjectContentActivity::breakdown([]))->toBe([])
        ->and(ProjectContentActivity::lastChangedAt([]))->toBe([]);
});

it('returns nothing for a project that does not exist', function () {
    expect(ProjectContentActivity::lastChangedAt(['nobody']))->toBe([]);
});

// ---------------------------------------------------------------------------
// items()
// ---------------------------------------------------------------------------

it('lists the edits behind a project, newest first', function () {
    $event = contentProject();

    $faq = Faq::factory()->create(['event_id' => $event->id]);
    $coverage = MediaCoverage::factory()->create(['event_id' => $event->id, 'title' => 'Featured in Kompas']);

    touchRow('faqs', $faq->id, '2026-08-07 12:00:00');
    touchRow('media_coverages', $coverage->id, '2026-08-07 09:00:00');

    $items = ProjectContentActivity::items('megabuild');

    expect($items[0]['source'])->toBe('faqs')
        ->and($items[0]['label'])->toBe('FAQ')
        ->and($items[1]['source'])->toBe('media_coverages')
        ->and($items[1]['title'])->toBe('Featured in Kompas');
});

it('reads a translatable title in the app locale', function () {
    $event = contentProject();

    $faq = Faq::factory()->create([
        'event_id' => $event->id,
        'question' => ['en' => 'Where is the venue?', 'id' => 'Di mana lokasinya?'],
    ]);
    touchRow('faqs', $faq->id, '2026-08-07 12:00:00');

    app()->setLocale('en');

    expect(ProjectContentActivity::items('megabuild')[0]['title'])->toBe('Where is the venue?');
});

it('falls back to another locale when the app locale is missing', function () {
    $event = contentProject();

    $faq = Faq::factory()->create([
        'event_id' => $event->id,
        'question' => ['id' => 'Di mana lokasinya?'],
    ]);
    touchRow('faqs', $faq->id, '2026-08-07 12:00:00');

    app()->setLocale('en');

    expect(ProjectContentActivity::items('megabuild')[0]['title'])->toBe('Di mana lokasinya?');
});

it('credits the user who deleted a row, not the previous editor', function () {
    $event = contentProject();

    $editor = User::factory()->create(['name' => 'First Editor']);
    $remover = User::factory()->create(['name' => 'Second Editor']);

    $faq = Faq::factory()->create(['event_id' => $event->id]);

    // What the models write: `updating` stamps updated_by, but a soft delete
    // goes through saveQuietly() and only stamps deleted_by.
    DB::table('faqs')->where('id', $faq->id)->update([
        'updated_by' => $editor->id,
        'deleted_by' => $remover->id,
        'deleted_at' => '2026-08-07 12:00:00',
        'updated_at' => '2026-08-07 12:00:00',
    ]);

    $item = ProjectContentActivity::items('megabuild')[0];

    expect($item['editor'])->toBe('Second Editor')
        ->and($item['deleted'])->toBeTrue();
});

it('names the editor of a row that is still there', function () {
    $event = contentProject();

    $editor = User::factory()->create(['name' => 'First Editor']);
    $faq = Faq::factory()->create(['event_id' => $event->id]);

    DB::table('faqs')->where('id', $faq->id)->update([
        'updated_by' => $editor->id,
        'updated_at' => '2026-08-07 12:00:00',
    ]);

    $item = ProjectContentActivity::items('megabuild')[0];

    expect($item['editor'])->toBe('First Editor')
        ->and($item['deleted'])->toBeFalse();
});

it('collapses gallery uploads into a single entry', function () {
    $event = contentProject();

    foreach (range(1, 30) as $i) {
        galleryPhoto($event, '2026-08-07 12:00:00');
    }

    $faq = Faq::factory()->create(['event_id' => $event->id]);
    touchRow('faqs', $faq->id, '2026-08-07 09:00:00');

    $items = ProjectContentActivity::items('megabuild', Carbon::parse('2026-08-01 00:00:00'));

    $gallery = array_values(array_filter($items, fn ($item) => $item['source'] === 'gallery'));

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['count'])->toBe(30)
        // A single import must not push every other edit out of the list.
        ->and(array_column($items, 'source'))->toContain('faqs');
});

it('leaves the gallery count out when there is no cut-off to count against', function () {
    $event = contentProject();
    galleryPhoto($event, '2026-08-07 12:00:00');

    $gallery = array_values(array_filter(
        ProjectContentActivity::items('megabuild'),
        fn ($item) => $item['source'] === 'gallery',
    ));

    expect($gallery[0]['count'])->toBeNull();
});

it('only lists edits made after the cut-off', function () {
    $event = contentProject();

    $shipped = Faq::factory()->create(['event_id' => $event->id]);
    $pending = Program::factory()->create(['event_id' => $event->id]);

    touchRow('faqs', $shipped->id, '2026-08-07 09:00:00');
    touchRow('programs', $pending->id, '2026-08-07 12:00:00');

    $items = ProjectContentActivity::items('megabuild', Carbon::parse('2026-08-07 10:00:00'));

    expect(array_column($items, 'source'))->toBe(['programs']);
});

it('shifts a UTC cut-off into local time before comparing', function () {
    $event = contentProject();

    $faq = Faq::factory()->create(['event_id' => $event->id]);
    // 06:00 Jakarta is 2026-08-06 23:00Z — older than the cut-off below, even
    // though a naive string comparison would call it newer.
    touchRow('faqs', $faq->id, '2026-08-07 06:00:00');

    $items = ProjectContentActivity::items('megabuild', Carbon::parse('2026-08-07T00:00:00Z'));

    expect($items)->toBe([]);
});

it('scopes items to one project', function () {
    $megabuild = contentProject('megabuild');
    $icc = contentProject('icc');

    $mine = Faq::factory()->create(['event_id' => $megabuild->id, 'question' => ['en' => 'Mine']]);
    $theirs = Faq::factory()->create(['event_id' => $icc->id, 'question' => ['en' => 'Theirs']]);

    touchRow('faqs', $mine->id, '2026-08-07 12:00:00');
    touchRow('faqs', $theirs->id, '2026-08-07 13:00:00');

    $faqs = array_filter(ProjectContentActivity::items('megabuild'), fn ($item) => $item['source'] === 'faqs');

    expect(array_column($faqs, 'title'))->toBe(['Mine']);
});

it('honours the limit', function () {
    $event = contentProject();

    foreach (range(1, 5) as $i) {
        $faq = Faq::factory()->create(['event_id' => $event->id]);
        touchRow('faqs', $faq->id, "2026-08-0{$i} 12:00:00");
    }

    expect(ProjectContentActivity::items('megabuild', null, 2))->toHaveCount(2);
});
