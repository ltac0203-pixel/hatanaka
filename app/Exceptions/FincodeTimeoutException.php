<?php

declare(strict_types=1);

namespace App\Exceptions;

class FincodeTimeoutException extends FincodeApiException
{
    public function __construct(string $message = 'Fincode APIへの接続がタイムアウトしました。')
    {
        parent::__construct($message, 0, []);
    }
}
