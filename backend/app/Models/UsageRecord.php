<?php

namespace App\Models;

use App\Enums\UsageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'type',
        'metric',
        'quantity',
        'meta',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => UsageType::class,
            'meta' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
