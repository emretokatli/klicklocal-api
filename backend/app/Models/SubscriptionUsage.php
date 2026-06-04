<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsage extends Model
{
    protected $table = 'subscription_usage';

    protected $fillable = [
        'workspace_id',
        'feature_key',
        'used_value',
        'reset_at',
    ];

    protected function casts(): array
    {
        return [
            'used_value' => 'integer',
            'reset_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
