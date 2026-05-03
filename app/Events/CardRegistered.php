<?php

declare(strict_types=1);

namespace App\Events;

class CardRegistered extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'card.created';
    }
}
