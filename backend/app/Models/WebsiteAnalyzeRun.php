<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebsiteAnalyzeRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'website',
        'status',
        'result',
        'error_message',
        'partial',
        'total_cost_usd',
        'num_turns',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'partial' => 'boolean',
            'total_cost_usd' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebsiteAnalyzeRun $run): void {
            if ($run->id === null) {
                $run->id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markCompleted(array $result, ?float $totalCostUsd = null, ?int $numTurns = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'partial' => false,
            'error_message' => null,
            'total_cost_usd' => $totalCostUsd,
            'num_turns' => $numTurns,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function markFailed(string $message, ?array $result = null, bool $partial = false, ?float $totalCostUsd = null, ?int $numTurns = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'result' => $result,
            'error_message' => $message,
            'partial' => $partial,
            'total_cost_usd' => $totalCostUsd,
            'num_turns' => $numTurns,
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        /** @var array<string, mixed>|null $result */
        $result = is_array($this->result) ? $this->result : null;

        return [
            'id' => $this->id,
            'website' => $this->website,
            'status' => $this->status,
            'partial' => $this->partial,
            'error_message' => $this->error_message,
            'score' => isset($result['score']) ? (int) $result['score'] : null,
            'band' => isset($result['band']) ? (string) $result['band'] : null,
            'has_report' => filled($result['report_markdown'] ?? null),
            'total_cost_usd' => $this->total_cost_usd,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'website' => $this->website,
            'status' => $this->status,
            'partial' => $this->partial,
            'error_message' => $this->error_message,
            'total_cost_usd' => $this->total_cost_usd,
            'num_turns' => $this->num_turns,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'result' => $this->isFinished() ? $this->result : null,
        ];
    }
}
