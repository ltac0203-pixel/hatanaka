<?php

namespace App\Exceptions;

class FincodeRateLimitException extends FincodeApiException
{
    protected ?int $retryAfterSeconds;

    public function __construct(string $message = 'Fincode APIのレート制限に達しました。', int $statusCode = 429, array $errorBody = [], ?int $retryAfterSeconds = null)
    {
        parent::__construct($message, $statusCode, $errorBody);
        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
