<?php

declare(strict_types=1);

namespace App\Services;

final readonly class RequestContext
{
    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
