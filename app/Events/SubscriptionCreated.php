<?php

declare(strict_types=1);

namespace App\Events;

class SubscriptionCreated extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'subscription.created';
    }
}
