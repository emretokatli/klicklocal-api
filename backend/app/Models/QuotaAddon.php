<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaAddon extends Model
{
    protected $fillable = [
        'workspace_id',
        'feature_key',
        'amount',
        'expires_at',
        'purchased_at',
        'price_paid',
        'provider',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'purchased_at' => 'datetime',
            'amount' => 'integer',
            'price_paid' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @param Builder<QuotaAddon> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
