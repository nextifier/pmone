<?php

namespace App\Support;

use Illuminate\Http\Request;

class PaginationClamp
{
    /**
     * Clamp a request's `per_page` to [1, $max], defaulting to $default when
     * absent. Bounds public pagination so `?per_page=100000` cannot trigger
     * an oversized eager-loaded query/response, and so the response cache
     * (which keys on the full URL) cannot mint a new entry per distinct
     * value. Callers must pass a `$max` that is >= `$default`, otherwise the
     * default itself would be clamped down and change existing behavior.
     */
    public static function perPage(Request $request, int $default, int $max = 100): int
    {
        return min(max((int) $request->input('per_page', $default), 1), $max);
    }
}
