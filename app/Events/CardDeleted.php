<?php

namespace App\Events;

class CardDeleted extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'card.deleted';
    }
}
