<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SubscriptionStatusChanged extends AbstractAuditableEvent
{
    public function __construct(
        Model $auditable,
        ?User $actor,
        array $oldValues,
        array $newValues,
        protected string $fromStatus,
        protected string $toStatus,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        parent::__construct(
            $auditable,
            $actor,
            $oldValues,
            $newValues,
            array_merge($metadata, [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ]),
            $ipAddress,
            $userAgent,
        );
    }

    public function auditEvent(): string
    {
        return 'subscription.status_changed';
    }

    public function fromStatus(): string
    {
        return $this->fromStatus;
    }

    public function toStatus(): string
    {
        return $this->toStatus;
    }
}
