<?php

namespace App\Exceptions;

use Exception;

class AuthServiceException extends Exception
{
    /**
     * @param array<int, array<string, mixed>> $errors
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $errors = []
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
