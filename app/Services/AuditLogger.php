<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    public function __construct(
        private readonly RequestContextResolver $requestContextResolver,
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

        // user_id は AuditLog の $fillable から外している（外部入力経由の汚染を避けるため）。
        // ここでは AuditLogger 経由の正規ルートで明示的に直接代入する。
        $auditLog = new AuditLog([
            'event' => $event,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress ?? $requestContext->ipAddress,
            'user_agent' => $userAgent ?? $requestContext->userAgent,
            'metadata' => $metadata,
        ]);
        $auditLog->user_id = $user?->id;
        $auditLog->save();

        return $auditLog;
    }
}
