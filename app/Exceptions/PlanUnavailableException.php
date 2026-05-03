<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PlanUnavailableException extends RuntimeException
{
    protected string $fincodePlanId;

    public function __construct(string $fincodePlanId, string $message = 'このプランは現在利用できません。')
    {
        parent::__construct($message);
        $this->fincodePlanId = $fincodePlanId;
    }

    public function getFincodePlanId(): string
    {
        return $this->fincodePlanId;
    }
}
