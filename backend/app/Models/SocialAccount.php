<?php

namespace App\Models;

use App\Enums\SocialAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'workspace_id',
        'provider',
        'provider_account_id',
        'account_name',
        'username',
        'status',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'metadata',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'token_expires_at' => 'datetime',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'status' => SocialAccountStatus::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function postPlatforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class);
    }

    public function isConnected(): bool
    {
        return $this->status === SocialAccountStatus::Connected
            && filled($this->access_token);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
