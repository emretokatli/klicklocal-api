<?php

namespace App\Models;

use App\Enums\BillingProvider;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'subscription_id',
        'provider',
        'provider_transaction_id',
        'amount',
        'currency',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
