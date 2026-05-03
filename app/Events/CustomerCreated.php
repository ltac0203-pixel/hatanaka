<?php

declare(strict_types=1);

namespace App\Events;

class CustomerCreated extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'customer.created';
    }
}
