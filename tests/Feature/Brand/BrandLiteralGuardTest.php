<?php

use Symfony\Component\Finder\Finder;

/**
 * Brand-layer rule: shared code never names a brand. This guard keeps the
 * user-visible backend output directories free of "PM One"/"pmone" literals
 * so a regression fails the suite instead of leaking into a whitelabel
 * deployment's emails or PDFs.
 */
it('keeps user-visible backend output brand-agnostic', function () {
    $allowlist = [
        // repo-relative path => reason
    ];

    $finder = Finder::create()
        ->files()
        ->in([resource_path('views'), app_path('Mail'), app_path('Notifications')])
        ->name('*.php');

    $violations = [];

    foreach ($finder as $file) {
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());

        if (array_key_exists($relative, $allowlist)) {
            continue;
        }

        if (preg_match('/pm one|pmone/i', $file->getContents()) === 1) {
            $violations[] = $relative;
        }
    }

    expect($violations)->toBe([]);
});
