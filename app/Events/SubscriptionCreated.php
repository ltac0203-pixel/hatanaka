<?php

namespace App\Events;

class SubscriptionCreated extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'subscription.created';
    }
}
