<?php

namespace App\Exceptions;

use Exception;

class VatlayerException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?array $responsePayload = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}




