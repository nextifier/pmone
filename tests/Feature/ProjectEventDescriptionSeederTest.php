<?php

use App\Models\Event;
use App\Models\Project;
use Database\Seeders\ProjectEventDescriptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds project bio as semantic HTML across all five locales', function () {
    $project = Project::factory()->create(['username' => 'megabuild']);

    $this->seed(ProjectEventDescriptionSeeder::class);

    $translations = $project->refresh()->getTranslations('bio');

    expect(array_keys($translations))
        ->toEqualCanonicalizing(['id', 'en', 'ja', 'ko', 'zh']);

    foreach (['id', 'en', 'ja', 'ko', 'zh'] as $locale) {
        expect($translations[$locale])->not->toBe('');
    }

    expect($translations['id'])
        ->toContain('<h2>Cari material bangunan tanpa keliling kota.</h2>')
        ->toContain('<p>')
        ->toContain('<h3>');

    expect($translations['en'])->toContain('Find building materials without driving across town.');

    // Six points -> six <h3> blocks.
    expect(substr_count($translations['id'], '<h3>'))->toBe(6);
});

it('seeds event description keyed by project and slug', function () {
    $project = Project::factory()->create(['username' => 'megabuild']);
    $event = Event::factory()->for($project)->create([
        'title' => 'Megabuild Indonesia 2026',
        'slug' => 'megabuild-indonesia-2026',
    ]);

    $this->seed(ProjectEventDescriptionSeeder::class);

    $translations = $event->refresh()->getTranslations('description');

    expect(array_keys($translations))->toEqualCanonicalizing(['id', 'en', 'ja', 'ko', 'zh']);
    expect($translations['id'])
        ->toContain('<h2>Empat hari untuk semua rencana bangunan Anda.</h2>');
    expect($translations['en'])
        ->toContain("Four days for everything you're building.");
});

it('resolves the shared comic con slug to the correct project row', function () {
    $icc = Project::factory()->create(['username' => 'icc']);
    $inacon = Project::factory()->create(['username' => 'inacon']);

    $slug = 'mybca-indonesia-comic-con-x-indonesia-anime-con-2025';
    $iccEvent = Event::factory()->for($icc)->create(['title' => 'ICC 2025', 'slug' => $slug]);
    $inaconEvent = Event::factory()->for($inacon)->create(['title' => 'INACON 2025', 'slug' => $slug]);

    $this->seed(ProjectEventDescriptionSeeder::class);

    // Same slug, different project: each row gets its own angle.
    expect($iccEvent->refresh()->getTranslation('description', 'en'))
        ->toContain('the 9th edition');
    expect($inaconEvent->refresh()->getTranslation('description', 'en'))
        ->toContain('anime and cosplay');
});

it('is idempotent and leaves unrelated projects untouched', function () {
    $project = Project::factory()->create(['username' => 'megabuild']);
    $unrelated = Project::factory()->create(['username' => 'nonexistent-in-docs', 'bio' => null]);

    $this->seed(ProjectEventDescriptionSeeder::class);
    $first = $project->refresh()->getTranslations('bio');

    $this->seed(ProjectEventDescriptionSeeder::class);
    $second = $project->refresh()->getTranslations('bio');

    expect($second)->toBe($first);
    expect($unrelated->refresh()->getTranslations('bio'))->toBe([]);
});
