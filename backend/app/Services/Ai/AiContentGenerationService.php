<?php

namespace App\Services\Ai;

use App\Models\AiGeneration;
use App\Models\BusinessProfile;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Authorization\AuthorizationService;
use App\Services\Media\MediaService;
use App\Services\Usage\UsageTrackingService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;
use Illuminate\Validation\ValidationException;

class AiContentGenerationService
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
        private readonly OpenAiClientInterface $client,
        private readonly AiPromptService $prompts,
        private readonly MediaService $mediaService,
        private readonly UsageTrackingService $usage,
    ) {}

    /**
     * @param  array{media_id?: int|null, prompt?: string|null}  $data
     */
    public function generate(User $user, int $workspaceId, array $data): AiGeneration
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        if (! $this->authorization->hasWorkspacePermission($user, $workspace, Permission::CREATE_POSTS)) {
            throw ValidationException::withMessages([
                'workspace' => ['You do not have permission to generate content in this workspace.'],
            ]);
        }

        $profile = $workspace->businessProfile;

        if ($profile === null || ! $profile->isComplete()) {
            throw ValidationException::withMessages([
                'business_profile' => ['Complete your business profile before generating content.'],
            ]);
        }

        $mediaId = $data['media_id'] ?? null;
        $imageUrl = $this->resolveImageUrl($workspace, $mediaId);

        $userPrompt = trim((string) ($data['prompt'] ?? ''));

        $generated = $this->client->generateContent(
            $this->systemPrompt($profile),
            $this->userPrompt($profile, $userPrompt),
            $imageUrl,
            $this->context($profile),
        );

        $generation = AiGeneration::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'media_id' => $mediaId,
            'prompt' => $userPrompt !== '' ? $userPrompt : null,
            'caption' => $generated->caption,
            'story_text' => $generated->storyText,
            'hashtags' => $generated->hashtags,
            'call_to_action' => $generated->callToAction,
            'model' => $generated->model,
            'tokens_used' => $generated->tokensUsed,
            'raw_response' => $generated->raw,
        ]);

        if ($generated->tokensUsed > 0) {
            $this->usage->recordAi($user, $workspace, 'content_generation', $generated->tokensUsed);
        } else {
            $this->usage->recordAi($user, $workspace, 'content_generation', 1);
        }

        return $generation->load(['media:id,file_name,file_path']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AiGeneration>
     */
    public function history(User $user, int $workspaceId, int $limit = 30)
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        return AiGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->with(['media:id,file_name,file_path', 'user:id,name'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function resolveImageUrl(Workspace $workspace, ?int $mediaId): ?string
    {
        if ($mediaId === null) {
            return null;
        }

        $media = Media::query()
            ->where('workspace_id', $workspace->id)
            ->find($mediaId);

        if ($media === null) {
            throw ValidationException::withMessages([
                'media_id' => ['Image does not belong to this workspace.'],
            ]);
        }

        return $this->mediaService->url($media);
    }

    private function systemPrompt(BusinessProfile $profile): string
    {
        $template = $this->prompts->activeTemplate('instagram_post_generator');

        if ($template !== null) {
            return $template->template;
        }

        return <<<'PROMPT'
        You are an expert German social media copywriter for local businesses.
        Write engaging, authentic Instagram content in German (Deutsch).
        Always respect the brand's tone of voice and stay on-brand.
        If an image is provided, describe and reference what is actually visible in it.

        Respond ONLY with a valid JSON object using exactly these keys:
        {
          "caption": "Instagram feed caption (2-4 sentences, with fitting emojis)",
          "story_text": "Short punchy text overlay for an Instagram Story",
          "hashtags": ["array", "of", "8-15", "relevant", "hashtags", "without spaces"],
          "call_to_action": "One clear call to action"
        }
        Do not include any text outside the JSON object.
        PROMPT;
    }

    private function userPrompt(BusinessProfile $profile, string $userPrompt): string
    {
        $lines = [
            'Business name: '.$profile->business_name,
            'Business type: '.($profile->business_type ?: 'n/a'),
            'City: '.($profile->city ?: 'n/a'),
            'Tone of voice: '.($profile->tone_of_voice ?: 'freundlich und einladend'),
            'Description: '.($profile->description ?: 'n/a'),
            'Products / services: '.($profile->products_services ?: 'n/a'),
        ];

        if ($userPrompt !== '') {
            $lines[] = 'Specific request for this post: '.$userPrompt;
        }

        $lines[] = 'Generate one Instagram post (caption, story text, hashtags, call to action) in German.';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private function context(BusinessProfile $profile): array
    {
        return [
            'business_name' => (string) $profile->business_name,
            'business_type' => (string) $profile->business_type,
            'city' => (string) $profile->city,
            'tone_of_voice' => (string) $profile->tone_of_voice,
            'description' => (string) $profile->description,
            'products_services' => (string) $profile->products_services,
        ];
    }
}
