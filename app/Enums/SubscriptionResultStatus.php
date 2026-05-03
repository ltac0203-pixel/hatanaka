<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionResultStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Pending = 'pending';
    case Canceled = 'canceled';
}
