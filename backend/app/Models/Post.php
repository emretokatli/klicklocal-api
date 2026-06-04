<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Post extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'title',
        'content',
        'media_id',
        'status',
        'scheduled_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
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

    public function platforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PostStatus::Draft;
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Scheduled;
    }

    public function isProcessing(): bool
    {
        return $this->status === PostStatus::Processing;
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    public function isFailed(): bool
    {
        return $this->status === PostStatus::Failed;
    }

    public function canBeScheduled(): bool
    {
        return $this->isDraft() || $this->isFailed();
    }

    public function markAsScheduled(Carbon $scheduledAt): void
    {
        $this->update([
            'scheduled_at' => $scheduledAt,
            'status' => PostStatus::Scheduled,
            'published_at' => null,
        ]);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => PostStatus::Processing]);
    }

    public function markAsPublished(): void
    {
        $this->update([
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => PostStatus::Failed]);
    }
}
