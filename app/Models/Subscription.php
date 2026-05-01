<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'fincode_plan_id',
        'plan_name',
        'plan_amount',
        'plan_interval',
        'plan_interval_count',
        'plan_snapshot',
        'fincode_subscription_id',
        'fincode_customer_id',
        'fincode_card_id',
        'status',
        'start_date',
        'stop_date',
        'next_charge_date',
        'canceled_at',
        'ends_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_amount' => 'integer',
            'plan_interval_count' => 'integer',
            'plan_snapshot' => 'array',
            'start_date' => 'date',
            'stop_date' => 'date',
            'next_charge_date' => 'date',
            'canceled_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FincodeCard::class, 'fincode_card_id', 'fincode_card_id');
    }

    public function results(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionResult::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active->value;
    }

    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::Canceled->value;
    }

    public function cancel(): void
    {
        $this->status = SubscriptionStatus::Canceled->value;
        $this->canceled_at = now();
        $this->stop_date = now()->toDateString();
        $this->save();
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNull($query->getModel()->getQualifiedDeletedAtColumn());
    }
}
