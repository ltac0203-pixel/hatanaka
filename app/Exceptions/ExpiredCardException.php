<?php

namespace App\Exceptions;

use RuntimeException;

class ExpiredCardException extends RuntimeException
{
    public function __construct(string $message = 'このカードは期限切れです。')
    {
        parent::__construct($message);
    }
}
