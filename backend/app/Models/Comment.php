<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = [
        'workspace_id', 'post_id', 'platform', 'external_id',
        'author', 'text', 'sentiment', 'commented_at', 'sentiment_classified_at',
        'suggested_reply', 'reply_text', 'replied_at',
    ];

    protected $casts = [
        'commented_at' => 'datetime',
        'sentiment_classified_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
