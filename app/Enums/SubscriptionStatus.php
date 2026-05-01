<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Unpaid = 'unpaid';
    case Incomplete = 'incomplete';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromApi(string $apiStatus): ?self
    {
        return self::tryFrom(strtolower($apiStatus));
    }
}
