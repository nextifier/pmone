<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Server-side verification of a Cloudflare Turnstile widget response token
 * against the `siteverify` endpoint. Purely a network call wrapper - the
 * decision of WHEN verification is required (per-event bot protection, a
 * configured secret, an admin bypass) lives in the caller (see
 * StorePublicTicketOrderRequest), not here, so this class never fails open.
 */
class Turnstile
{
    protected const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Verify a Turnstile response token. Returns false for a missing token, a
     * missing secret, a non-2xx/transport failure, or a Cloudflare-reported
     * failure - never throws, so a flaky Cloudflare call cannot 500 the
     * checkout.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        $secret = config('turnstile.secret');

        if (empty($secret) || empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->connectTimeout(3)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            return $response->successful() && (bool) $response->json('success', false);
        } catch (\Throwable) {
            return false;
        }
    }
}
