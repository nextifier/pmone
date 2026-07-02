<?php

namespace App\Support\FileNamer;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

/**
 * Appends a short random token to every stored file name.
 *
 * The collection-based path generator stores every file of a collection in one
 * folder keyed on {model}/{collection}/{model_id}, so two uploads sharing an
 * original name would otherwise resolve to an identical path + URL. That makes a
 * same-name replacement invisible to every cache layer (browser, CDN,
 * responsecache) and, for single-file collections, lets the surplus-media cleanup
 * delete the freshly written file. A unique file name makes each upload resolve to
 * a fresh path/URL, busting caches and avoiding the collision. Existing media keep
 * their stored file_name, so their URLs are unaffected.
 */
class UniqueFileNamer extends DefaultFileNamer
{
    public function originalFileName(string $fileName): string
    {
        $base = parent::originalFileName($fileName) ?: 'file';

        return $base.'-'.Str::lower(Str::random(6));
    }
}
