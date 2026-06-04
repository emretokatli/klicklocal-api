<?php

namespace App\Services\Usage;

use App\Enums\UsageType;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

class UsageTrackingService
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        UsageType $type,
        string $metric,
        int $quantity = 1,
        ?User $user = null,
        ?Workspace $workspace = null,
        ?array $meta = null,
    ): UsageRecord {
        return UsageRecord::create([
            'user_id' => $user?->id,
            'workspace_id' => $workspace?->id,
            'type' => $type,
            'metric' => $metric,
            'quantity' => $quantity,
            'meta' => $meta,
            'recorded_at' => now(),
        ]);
    }

    public function recordAi(User $user, Workspace $workspace, string $metric, int $tokens = 1): UsageRecord
    {
        return $this->record(UsageType::Ai, $metric, $tokens, $user, $workspace, [
            'unit' => 'tokens',
        ]);
    }

    public function recordSocialApi(Workspace $workspace, string $provider, int $calls = 1): UsageRecord
    {
        return $this->record(UsageType::SocialApi, $provider, $calls, null, $workspace);
    }

    public function recordQueueJob(Workspace $workspace, string $job): UsageRecord
    {
        return $this->record(UsageType::QueueJob, $job, 1, null, $workspace);
    }

    public function recordStorage(Workspace $workspace, int $bytes): UsageRecord
    {
        return $this->record(UsageType::Storage, 'bytes', $bytes, null, $workspace);
    }

    /**
     * @return array<string, int>
     */
    public function summarizeWorkspace(Workspace $workspace, ?Carbon $from = null): array
    {
        $from ??= now()->startOfMonth();

        $rows = UsageRecord::query()
            ->where('workspace_id', $workspace->id)
            ->where('recorded_at', '>=', $from)
            ->selectRaw('type, SUM(quantity) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'ai' => (int) ($rows[UsageType::Ai->value] ?? 0),
            'social_api' => (int) ($rows[UsageType::SocialApi->value] ?? 0),
            'queue_job' => (int) ($rows[UsageType::QueueJob->value] ?? 0),
            'storage' => (int) ($rows[UsageType::Storage->value] ?? 0),
        ];
    }
}
