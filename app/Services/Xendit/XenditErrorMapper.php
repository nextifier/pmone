<?php

namespace App\Services\Xendit;

use Throwable;
use Xendit\XenditSdkException;

/**
 * Maps Xendit SDK exceptions and network errors to user-friendly error
 * codes + messages. Used by public controllers and admin retry flows so
 * customers see actionable text instead of "try again in a moment" for
 * problems that require staff intervention (IP allowlist, bad credentials,
 * suspended account).
 *
 * The mapper is intentionally permissive: it falls back to a generic
 * "temporarily unavailable" message rather than leaking raw Xendit text.
 */
class XenditErrorMapper
{
    /**
     * @return array{error_code: string, http_status: int, message: string, log_level: string}
     */
    public static function map(Throwable $e): array
    {
        if ($e instanceof XenditSdkException) {
            return self::mapXenditSdk($e);
        }

        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out') || str_contains($msg, 'curl error 28')) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_TIMEOUT',
                'http_status' => 504,
                'message' => 'Payment provider is taking too long to respond. Please try again in a moment.',
                'log_level' => 'warning',
            ];
        }

        if (str_contains($msg, 'could not resolve host') || str_contains($msg, 'connection refused') || str_contains($msg, 'network')) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_UNREACHABLE',
                'http_status' => 503,
                'message' => 'Cannot reach payment provider. Please check your internet connection and try again.',
                'log_level' => 'warning',
            ];
        }

        return [
            'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
            'http_status' => 503,
            'message' => 'Could not generate payment link. Please try again in a moment.',
            'log_level' => 'error',
        ];
    }

    /**
     * @return array{error_code: string, http_status: int, message: string, log_level: string}
     */
    private static function mapXenditSdk(XenditSdkException $e): array
    {
        $code = (string) $e->getErrorCode();
        $msg = strtolower((string) $e->getErrorMessage());
        $status = (int) $e->getStatus();

        // IP allowlist - account-level config, blocks all calls until staff adds the server IP.
        if ($code === 'IP_NOT_ALLOWED' || str_contains($msg, 'ip allowlist') || str_contains($msg, "hasn't been added")) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_IP_NOT_ALLOWED',
                'http_status' => 503,
                'message' => 'Payment provider blocked our server. Staff needs to add the server IP to the Xendit allowlist. Please contact support.',
                'log_level' => 'error',
            ];
        }

        // Invalid credentials - secret key wrong or revoked.
        if ($code === 'INVALID_API_KEY' || str_contains($msg, 'invalid api key') || str_contains($msg, 'api key')) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_MISCONFIGURED',
                'http_status' => 503,
                'message' => 'Payment provider credentials are invalid. Please contact support to resolve this.',
                'log_level' => 'error',
            ];
        }

        // Rate limited - retry later may help.
        if ($status === 429 || str_contains($code, 'RATE_LIMIT') || str_contains($msg, 'rate limit') || str_contains($msg, 'too many request')) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_RATE_LIMITED',
                'http_status' => 503,
                'message' => 'Too many payment requests. Please wait a minute and try again.',
                'log_level' => 'warning',
            ];
        }

        // Account/Permission issues - staff intervention needed.
        if (in_array($code, ['ACCOUNT_SUSPENDED', 'ACCESS_DENIED', 'FORBIDDEN'], true) || $status === 403) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_FORBIDDEN',
                'http_status' => 503,
                'message' => 'Payment provider rejected the request. Please contact support to resolve this.',
                'log_level' => 'error',
            ];
        }

        // Duplicate transaction - idempotency issue, treat as soft error.
        if ($code === 'DUPLICATE_TRANSACTION' || str_contains($msg, 'duplicate')) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_DUPLICATE',
                'http_status' => 409,
                'message' => 'A payment link already exists for this booking. Please refresh and try again.',
                'log_level' => 'warning',
            ];
        }

        // Validation error from Xendit - usually our payload issue (bad email, amount, etc).
        if ($code === 'API_VALIDATION_ERROR' || $status === 400) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_VALIDATION',
                'http_status' => 422,
                'message' => 'Payment provider rejected the booking data. Please contact support.',
                'log_level' => 'error',
            ];
        }

        // Server-side Xendit error - usually transient.
        if ($status >= 500) {
            return [
                'error_code' => 'PAYMENT_GATEWAY_SERVER_ERROR',
                'http_status' => 503,
                'message' => 'Payment provider is having issues. Please try again in a few minutes.',
                'log_level' => 'warning',
            ];
        }

        return [
            'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
            'http_status' => 503,
            'message' => 'Could not generate payment link. Please try again in a moment.',
            'log_level' => 'error',
        ];
    }
}
