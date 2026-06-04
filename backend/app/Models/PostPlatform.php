<?php

namespace App\Models;

use App\Enums\PostPlatformStatus;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostPlatform extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'social_account_id',
        'status',
        'platform_post_id',
        'response_data',
        'failure_reason',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostPlatformStatus::class,
            'response_data' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function isPending(): bool
    {
        return $this->status === PostPlatformStatus::Pending;
    }

    public function isPublished(): bool
    {
        return $this->status === PostPlatformStatus::Published;
    }

    public function isFailed(): bool
    {
        return $this->status === PostPlatformStatus::Failed;
    }

    public function markAsPublished(PublishResponseDTO $response): void
    {
        $this->update([
            'status' => PostPlatformStatus::Published,
            'platform_post_id' => $response->platformPostId,
            'failure_reason' => null,
            'response_data' => $response->rawResponse,
            'published_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function markAsFailed(
        string $reason,
        ?array $rawResponse = null,
        ?string $platformPostId = null,
    ): void {
        $this->update([
            'status' => PostPlatformStatus::Failed,
            'failure_reason' => $reason,
            'response_data' => $rawResponse,
            'platform_post_id' => $platformPostId,
            'published_at' => null,
        ]);
    }
}
