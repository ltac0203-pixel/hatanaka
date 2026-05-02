<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FincodeCard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'fincode_customer_id',
        'fincode_card_id',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'holder_name',
        'is_default',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fincodeCustomer(): BelongsTo
    {
        return $this->belongsTo(FincodeCustomer::class, 'fincode_customer_id', 'fincode_customer_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->brand} ****{$this->last4}";
    }

    public function getExpiryDisplayAttribute(): string
    {
        return str_pad((string) $this->exp_month, 2, '0', STR_PAD_LEFT).'/'.$this->exp_year;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    public function isExpired(): bool
    {
        // アプリのタイムゾーンに揃え、UTC との時差で月末跨ぎがブレる境界バグを防ぐ。
        $appTimezone = (string) (config('app.timezone') ?: 'UTC');
        $now = Carbon::now($appTimezone);
        $expiryDate = Carbon::create($this->exp_year, $this->exp_month, 1, 0, 0, 0, $appTimezone)
            ->endOfMonth();

        return $now->isAfter($expiryDate);
    }
}
