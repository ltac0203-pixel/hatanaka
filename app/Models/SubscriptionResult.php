<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionResult extends Model
{
    protected $fillable = [
        'subscription_id',
        'user_id',
        'fincode_subscription_id',
        'fincode_payment_id',
        'status',
        'amount',
        'tax',
        'charged_at_date',
        'charged_at',
        'error_code',
        'error_message',
        'fincode_response',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tax' => 'integer',
            'charged_at_date' => 'date',
            'charged_at' => 'datetime',
            'fincode_response' => 'array',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
