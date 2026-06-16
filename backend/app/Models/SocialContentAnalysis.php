<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialContentAnalysis extends Model
{
    protected $fillable = [
        'workspace_id',
        'social_account_id',
        'provider',
        'external_id',
        'post_type',
        'caption',
        'permalink',
        'published_at',
        'hour',
        'likes',
        'comments',
        'shares',
        'reach',
        'impressions',
        'engagement',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'hour' => 'integer',
            'likes' => 'integer',
            'comments' => 'integer',
            'shares' => 'integer',
            'reach' => 'integer',
            'impressions' => 'integer',
            'engagement' => 'integer',
            'raw' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
