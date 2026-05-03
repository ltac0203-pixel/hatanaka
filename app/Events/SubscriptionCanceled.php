<?php

declare(strict_types=1);

namespace App\Events;

class SubscriptionCanceled extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'subscription.canceled';
    }
}
