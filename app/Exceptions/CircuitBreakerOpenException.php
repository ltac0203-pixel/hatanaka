<?php

namespace App\Exceptions;

class CircuitBreakerOpenException extends FincodeApiException
{
    protected int $remainingSeconds;

    public function __construct(int $remainingSeconds, string $message = 'Fincode APIへの接続が一時的に遮断されています。')
    {
        parent::__construct($message, 503, []);
        $this->remainingSeconds = $remainingSeconds;
    }

    public function getRemainingSeconds(): int
    {
        return $this->remainingSeconds;
    }
}
