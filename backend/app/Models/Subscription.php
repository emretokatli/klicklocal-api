<?php

namespace App\Models;

use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'workspace_id',
        'plan_id',
        'provider',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'renewal_at',
        'provider_customer_id',
        'provider_subscription_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewal_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        if (in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)) {
            if ($this->ends_at && $this->ends_at->isPast()) {
                return false;
            }

            return true;
        }

        return false;
    }
}
