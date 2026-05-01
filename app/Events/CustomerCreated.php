<?php

namespace App\Events;

class CustomerCreated extends AbstractAuditableEvent
{
    public function auditEvent(): string
    {
        return 'customer.created';
    }
}
