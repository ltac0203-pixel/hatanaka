<?php

declare(strict_types=1);

namespace App\Exceptions;

class FincodeServerException extends FincodeApiException
{
    public function __construct(string $message = 'Fincode APIでサーバーエラーが発生しました。', int $statusCode = 500, array $errorBody = [])
    {
        parent::__construct($message, $statusCode, $errorBody);
    }
}
