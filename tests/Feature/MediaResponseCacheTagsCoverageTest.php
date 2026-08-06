<?php

use App\Models\Ticket;
use App\Support\MediaResponseCacheTags;
use App\Traits\ClearsResponseCache;
use Spatie\MediaLibrary\HasMedia;

/**
 * Every model that (a) owns media and (b) declares response-cache tags must be
 * listed in MediaResponseCacheTags.
 *
 * Why this test exists: the map is consulted by two call sites that bypass the
 * owner's Eloquent events entirely — MediaController::clearOwnerResponseCache
 * (the generic /media/* endpoints) and ClearResponseCacheOnConversionCompleted
 * (queued conversions land long after the controller's own clear). Both fall
 * back to `[]` on an unmapped class and then silently skip the clear. Ticket,
 * Program, ProjectBanner and Form were all missing on 6 Aug 2026; the Ticket
 * hole is why a replaced ticket poster stayed live on the event websites for
 * hours. Nothing failed, nothing logged.
 *
 * A model with media but NO response-cache tags is fine: it is not rendered into
 * any cached public payload, so there is nothing to invalidate.
 */
function mediaOwningModelsWithCacheTags(): array
{
    $models = [];

    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! $reflection->implementsInterface(HasMedia::class)) {
            continue;
        }

        if (! in_array(ClearsResponseCache::class, class_uses_recursive($class), true)) {
            continue;
        }

        $models[class_basename($class)] = $class;
    }

    return $models;
}

it('finds media-owning models to check', function () {
    expect(mediaOwningModelsWithCacheTags())->not->toBeEmpty();
});

it('maps every media-owning, cache-tagged model to at least one tag', function () {
    $unmapped = [];

    foreach (mediaOwningModelsWithCacheTags() as $name => $class) {
        if (MediaResponseCacheTags::for($class) === []) {
            $unmapped[] = $name;
        }
    }

    expect($unmapped)->toBe([], sprintf(
        'Add these to app/Support/MediaResponseCacheTags.php or their media edits will invalidate nothing: %s',
        implode(', ', $unmapped),
    ));
});

it('maps a ticket poster onto the tickets tag', function () {
    expect(MediaResponseCacheTags::for(Ticket::class))->toContain('tickets');
});

it('returns no tags for a class it has never heard of', function () {
    expect(MediaResponseCacheTags::for('App\\Models\\NotARealModel'))->toBe([]);
});
