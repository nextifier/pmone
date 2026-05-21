<?php

namespace App\Exceptions\Payment;

use Exception;
use Throwable;

/**
 * Provider-agnostic failure raised by any payment provider implementation
 * (XenditService today, a future MidtransService) when an API call cannot be
 * completed. Carries a stable, user-safe error code and HTTP status so
 * controllers surface a consistent response regardless of which provider failed.
 */
class PaymentProviderException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'PAYMENT_GATEWAY_UNAVAILABLE',
        public readonly int $httpStatus = 503,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
