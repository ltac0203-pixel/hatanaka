<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** 同一リクエスト内での重複クエリを防ぐキャッシュ。 */
    private ?bool $activeSubscriptionCache = null;

    public function fincodeCustomer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FincodeCustomer::class);
    }

    public function fincodeCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FincodeCard::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', SubscriptionStatus::Active->value);
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscriptionCache ??= $this->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->exists();
    }

    public function getDefaultCard(): ?FincodeCard
    {
        return $this->fincodeCards()->where('is_default', true)->first();
    }
}
