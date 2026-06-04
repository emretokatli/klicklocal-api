<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Actions\Billing\IncrementFeatureUsageAction;
use App\Enums\PlanFeature;
use App\Services\Billing\FeatureAccessService;
use App\Services\Usage\UsageTrackingService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaService
{
    /**
     * @var list<string>
     */
    private array $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/quicktime',
    ];

    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
        private readonly FeatureAccessService $features,
        private readonly IncrementFeatureUsageAction $incrementUsage,
        private readonly UsageTrackingService $usageTracking,
    ) {}

    public function upload(User $user, int $workspaceId, UploadedFile $file): Media
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        if (! $this->authorization->hasWorkspacePermission($user, $workspace, Permission::UPLOAD_MEDIA)) {
            throw ValidationException::withMessages([
                'workspace' => ['You do not have permission to upload media to this workspace.'],
            ]);
        }

        $this->features->assertCanUseFeature($workspace, PlanFeature::MediaUploadsMonthly);

        if (! in_array($file->getMimeType(), $this->allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => ['File type not allowed.'],
            ]);
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $fileName = Str::uuid().'.'.$extension;
        $directory = 'media/'.$workspace->id;
        $path = $file->storeAs($directory, $fileName, 'public');

        $media = Media::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'created_at' => now(),
        ]);

        $bytes = (int) $file->getSize();
        $this->incrementUsage->execute($workspace, PlanFeature::MediaUploadsMonthly);
        $mb = (int) ceil($bytes / 1024 / 1024);
        if ($mb > 0) {
            $this->incrementUsage->execute($workspace, PlanFeature::StorageLimitMb, $mb);
        }
        $this->usageTracking->recordStorage($workspace, $bytes);

        return $media;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Media>
     */
    public function list(User $user, int $workspaceId)
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        return Media::query()
            ->where('workspace_id', $workspace->id)
            ->latest('created_at')
            ->get();
    }

    public function url(Media $media): string
    {
        return rtrim(config('app.url'), '/').'/storage/'.$media->file_path;
    }
}
