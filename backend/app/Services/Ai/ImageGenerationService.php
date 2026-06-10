<?php

namespace App\Services\Ai;

use App\Models\BusinessProfile;
use App\Models\User;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\GeneratedImageDTO;
use App\Services\Authorization\AuthorizationService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;
use Illuminate\Validation\ValidationException;

class ImageGenerationService
{
    public function __construct(
        private readonly OpenAiClientInterface $client,
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
    ) {}

    public function generate(
        User $user,
        int $workspaceId,
        string $userPrompt = '',
        string $platform = 'instagram',
        string $contentType = 'post',
        string $size = '1024x1024',
    ): GeneratedImageDTO {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        if (! $this->authorization->hasWorkspacePermission($user, $workspace, Permission::CREATE_POSTS)) {
            throw ValidationException::withMessages([
                'workspace' => ['You do not have permission to generate images in this workspace.'],
            ]);
        }

        $profile = $workspace->businessProfile;

        if ($profile === null || ! $profile->isComplete()) {
            throw ValidationException::withMessages([
                'business_profile' => ['Complete your business profile before generating images.'],
            ]);
        }

        $prompt = $this->buildPrompt($profile, $userPrompt, $platform, $contentType);
        $context = $this->buildContext($profile);

        return $this->client->generateImage($prompt, $context, $size);
    }

    private function buildPrompt(
        BusinessProfile $profile,
        string $userPrompt,
        string $platform,
        string $contentType,
    ): string {
        $parts = [
            "Professional social media {$contentType} image for a {$profile->business_type} called \"{$profile->business_name}\"",
        ];

        if ($profile->city) {
            $parts[] = "located in {$profile->city}, Germany";
        }

        if ($profile->description) {
            $parts[] = "Business description: {$profile->description}";
        }

        if ($profile->tone_of_voice) {
            $parts[] = "Visual style should match tone: {$profile->tone_of_voice}";
        }

        $aspectHint = match ($platform) {
            'tiktok', 'instagram_reel' => 'vertical 9:16 format',
            'instagram_story'          => 'vertical 9:16 format',
            default                    => 'square 1:1 format suitable for Instagram feed',
        };
        $parts[] = "Format: {$aspectHint}";
        $parts[] = 'High quality, photorealistic, suitable for local business marketing.';
        $parts[] = 'No text overlays.';

        if ($userPrompt !== '') {
            $parts[] = "Specific request: {$userPrompt}";
        }

        return implode('. ', $parts);
    }

    /** @return array<string, string> */
    private function buildContext(BusinessProfile $profile): array
    {
        return [
            'business_name' => (string) $profile->business_name,
            'business_type' => (string) $profile->business_type,
            'city'          => (string) $profile->city,
        ];
    }
}
