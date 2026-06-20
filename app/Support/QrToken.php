<?php

namespace App\Support;

class QrToken
{
    /**
     * Normalize a scanned QR value to the bare attendee token. Badge labels were
     * historically printed as a verify URL (https://pmone.id/v/<token>), while
     * e-tickets encode the bare token; this strips that URL wrapper plus any
     * trailing path/query/fragment so both forms resolve to the same token. A
     * bare token passes through unchanged.
     */
    public static function normalize(?string $raw): string
    {
        $value = trim((string) $raw);

        if (($pos = stripos($value, '/v/')) !== false) {
            $value = substr($value, $pos + 3);
        }

        return trim((string) preg_replace('~[/?#].*$~', '', $value));
    }
}
