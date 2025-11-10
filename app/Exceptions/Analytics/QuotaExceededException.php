<?php

namespace App\Exceptions\Analytics;

use Exception;

class QuotaExceededException extends Exception
{
    /**
     * Create a new quota exceeded exception.
     */
    public function __construct(
        string $message = 'Google Analytics API quota exceeded',
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $propertyId = null
    ) {
        parent::__construct($message);
    }

    /**
     * Get retry after time in seconds.
     */
    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * Get property ID that caused the quota error.
     */
    public function getPropertyId(): ?string
    {
        return $this->propertyId;
    }
}
