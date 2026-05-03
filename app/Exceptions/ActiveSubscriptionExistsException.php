<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ActiveSubscriptionExistsException extends RuntimeException
{
    public function __construct(
        string $message = '既にアクティブなサブスクリプションがあります。',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
