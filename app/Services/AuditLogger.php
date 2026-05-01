<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function __construct(
        private RequestContextResolver $requestContextResolver
    ) {}

    /**
     * 変更操作を監査証跡として残し、後から追跡できるようにする。
     */
    public function log(
        string $event,
        Model $auditable,
        ?User $user = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        $requestContext = $this->requestContextResolver->resolve();

        return AuditLog::create([
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress ?? $requestContext->ipAddress,
            'user_agent' => $userAgent ?? $requestContext->userAgent,
            'metadata' => $metadata,
        ]);
    }
}
