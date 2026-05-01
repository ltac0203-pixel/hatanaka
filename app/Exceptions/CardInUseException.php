<?php

namespace App\Exceptions;

use RuntimeException;

class CardInUseException extends RuntimeException
{
    public function __construct(string $message = 'アクティブなサブスクリプションで使用中のカードは削除できません。')
    {
        parent::__construct($message);
    }
}
