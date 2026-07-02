<?php

use App\Support\FileNamer\UniqueFileNamer;

it('appends a lowercase random token to the original file name', function () {
    $name = (new UniqueFileNamer)->originalFileName('poster.jpg');

    expect($name)->toMatch('/^poster-[a-z0-9]{6}$/');
});

it('produces a different token on each call', function () {
    $namer = new UniqueFileNamer;

    expect($namer->originalFileName('poster.jpg'))
        ->not->toBe($namer->originalFileName('poster.jpg'));
});

it('falls back to a base name when the stem is empty', function () {
    expect((new UniqueFileNamer)->originalFileName('.jpg'))
        ->toMatch('/^file-[a-z0-9]{6}$/');
});
