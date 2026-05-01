<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FincodeCustomer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'fincode_customer_id',
        'name',
        'email',
        'phone_cc',
        'phone_no',
        'addr_country',
        'addr_state',
        'addr_city',
        'addr_line_1',
        'addr_line_2',
        'addr_post_code',
        'metadata',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FincodeCard::class, 'fincode_customer_id', 'fincode_customer_id');
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class, 'fincode_customer_id', 'fincode_customer_id');
    }
}
