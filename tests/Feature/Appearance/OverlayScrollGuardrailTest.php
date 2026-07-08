<?php

use Illuminate\Support\Facades\File;

/*
 * Guardrail for the "Dialog/AlertDialog scrollable only in Mono" bug.
 *
 * Functional scroll CSS (max-height cap + overflow) for overlay content must live
 * in the shadcn-vue *component* base class, so it applies under every appearance
 * "style". It must NOT be pushed into per-style `.cn-*` CSS, because a style that
 * forgets it (all styles except mono did) makes tall overlays overflow the viewport
 * with unreachable footer buttons.
 */

it('keeps overlay scroll utilities in the component base class', function () {
    $dir = base_path('frontend/app/components/ui');

    foreach (['dialog/DialogContent.vue', 'alert-dialog/AlertDialogContent.vue'] as $rel) {
        $src = File::get("{$dir}/{$rel}");

        expect($src)->toContain('max-h-[calc(100%-4rem)]')
            ->and($src)->toContain('overflow-y-auto');
    }
});

it('does not reintroduce overlay scroll utilities into per-style CSS', function () {
    $files = File::glob(base_path('frontend/app/assets/css/styles/*.css'));

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $css = File::get($file);

        foreach (['cn-dialog-content', 'cn-alert-dialog-content'] as $selector) {
            if (! preg_match('/\.'.preg_quote($selector, '/').'\s*\{\s*@apply([^;]*);/', $css, $m)) {
                continue;
            }

            $apply = $m[1];
            $where = basename($file)." .{$selector}";

            expect($apply)->not->toContain('max-h-', "{$where} must not cap height (belongs in the component)")
                ->and($apply)->not->toContain('overflow-y', "{$where} must not own scroll (belongs in the component)");
        }
    }
});
