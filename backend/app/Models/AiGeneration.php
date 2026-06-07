<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'media_id',
        'prompt',
        'caption',
        'story_text',
        'hashtags',
        'call_to_action',
        'model',
        'tokens_used',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'hashtags' => 'array',
            'raw_response' => 'array',
            'tokens_used' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
