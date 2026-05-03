<?php

declare(strict_types=1);

namespace App\Events;

class CardDeleted extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'card.deleted';
    }
}
