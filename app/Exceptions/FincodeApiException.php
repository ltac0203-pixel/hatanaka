<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class FincodeApiException extends Exception
{
    protected int $statusCode;

    protected array $errorBody;

    public function __construct(string $message, int $statusCode = 0, array $errorBody = [])
    {
        parent::__construct($message, $statusCode);

        $this->statusCode = $statusCode;
        $this->errorBody = $errorBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorBody(): array
    {
        return $this->errorBody;
    }
}
