<?php

namespace App\Exceptions;

use RuntimeException;

/** Exception domain: otomatis dirender menjadi JSON error standar. */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'ERROR',
        public readonly int $status = 400,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }
}
