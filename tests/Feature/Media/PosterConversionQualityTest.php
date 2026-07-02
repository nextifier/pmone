<?php

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function posterTmpFile(int $w, int $h): string
{
    $file = UploadedFile::fake()->image('poster.jpg', $w, $h);
    $path = sys_get_temp_dir().'/poster_'.uniqid().'.jpg';
    copy($file->getRealPath(), $path);

    return $path;
}

beforeEach(function () {
    Storage::fake('public');
    // Run every conversion inline so queued md/lg/xl are generated during the test.
    config()->set('media-library.queue_conversions_by_default', false);
});

test('event poster xl is width-only at 1600 and preserves portrait aspect', function () {
    $event = Event::factory()->create();

    $event->addMedia(posterTmpFile(2000, 2500))->toMediaCollection('poster_image');
    $media = $event->getFirstMedia('poster_image');

    expect($media->hasGeneratedConversion('xl'))->toBeTrue();

    [$w, $h] = getimagesizefromstring(
        Storage::disk('public')->get($media->getPathRelativeToRoot('xl'))
    );

    expect($w)->toBe(1600)          // bumped from 1080
        ->and($h)->toBeGreaterThan($w); // width-only: no longer square-cropped
});

test('ticket poster xl conversion is 1600 wide', function () {
    $ticket = Ticket::factory()->create();

    $ticket->addMedia(posterTmpFile(2000, 2500))->toMediaCollection('poster');
    $media = $ticket->getFirstMedia('poster');

    expect($media->hasGeneratedConversion('xl'))->toBeTrue();

    [$w] = getimagesizefromstring(
        Storage::disk('public')->get($media->getPathRelativeToRoot('xl'))
    );

    expect($w)->toBe(1600);
});
