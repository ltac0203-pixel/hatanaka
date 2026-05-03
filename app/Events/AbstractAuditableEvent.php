<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Contracts\AuditableEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class AbstractAuditableEvent implements AuditableEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        protected Model $auditable,
        protected ?User $actor = null,
        protected array $oldValues = [],
        protected array $newValues = [],
        protected array $metadata = [],
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function auditable(): Model
    {
        return $this->auditable;
    }

    public function actor(): ?User
    {
        return $this->actor;
    }

    public function oldValues(): array
    {
        return $this->oldValues;
    }

    public function newValues(): array
    {
        return $this->newValues;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
