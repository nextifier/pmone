<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSheetsToken
{
    /**
     * Guard the Google Sheets feeds with the shared token from
     * `services.sheets.api_token`, read from the `?token=` query parameter
     * because Apps Script's UrlFetchApp call cannot set request headers.
     *
     * An unset token rejects every request rather than matching a missing
     * `?token=` parameter.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.sheets.api_token');

        if ($token === '' || ! hash_equals($token, (string) $request->query('token'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
