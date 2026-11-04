<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'kind',
        'percent',
        'expires_at',
        'max_uses',
        'once_per_customer',
        'is_active',
        'assigned_email',
        'assigned_user_id',
        'services',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'once_per_customer' => 'boolean',
            'is_active' => 'boolean',
            'services' => 'array',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function isSingleUse(): bool
    {
        return $this->max_uses === 1 || $this->once_per_customer;
    }
}
