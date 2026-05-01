<?php

namespace App\Events\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AuditableEvent
{
    public function auditEvent(): string;

    public function auditable(): Model;

    public function actor(): ?User;

    public function oldValues(): array;

    public function newValues(): array;

    public function metadata(): array;
}
