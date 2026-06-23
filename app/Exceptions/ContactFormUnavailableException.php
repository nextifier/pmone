<?php

namespace App\Exceptions;

use Exception;

class ContactFormUnavailableException extends Exception
{
    public function __construct(string $message = 'Contact form is not available for this project.')
    {
        parent::__construct($message);
    }
}
