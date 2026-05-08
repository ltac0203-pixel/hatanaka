<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * Fincode が返しうる表記揺れ (month/monthly, year/yearly 等) を吸収して enum に正規化する。
     * 不明な値は monthly にフォールバックさせ、画面側の安全側デフォルトに揃える。
     */
    public static function fromApi(string $value): self
    {
        return match (strtolower($value)) {
            'month', 'monthly' => self::Monthly,
            'year', 'yearly' => self::Yearly,
            'week', 'weekly' => self::Weekly,
            'day', 'daily' => self::Daily,
            default => self::Monthly,
        };
    }

    public function label(int $intervalCount = 1): string
    {
        $unit = match ($this) {
            self::Daily => '日',
            self::Weekly => '週',
            self::Monthly => '月',
            self::Yearly => '年',
        };

        return $intervalCount > 1 ? "{$intervalCount}{$unit}" : $unit;
    }

    public function priceDisplay(int $amount, int $intervalCount = 1): string
    {
        return '¥'.number_format($amount).'/'.$this->label($intervalCount);
    }
}
