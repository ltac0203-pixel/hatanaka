<?php

namespace App\Events;

class CardRegistered extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'card.created';
    }
}
