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
     * @param  array{media_id?: int|null, prompt?: string|null, platform?: string|null, content_type?: string|null, seo_focus?: string|null}  $data
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

        $userPrompt  = trim((string) ($data['prompt'] ?? ''));
        $platform    = $data['platform']    ?? 'instagram';
        $contentType = $data['content_type'] ?? 'post';
        $seoFocus    = $data['seo_focus']    ?? null;

        $generated = $this->client->generateContent(
            $this->systemPrompt($profile, $platform, $contentType),
            $this->userPrompt($profile, $userPrompt, $seoFocus),
            $imageUrl,
            $this->context($profile),
        );

        $generation = AiGeneration::create([
            'workspace_id' => $workspace->id,
            'user_id'      => $user->id,
            'media_id'     => $mediaId,
            'prompt'       => $userPrompt !== '' ? $userPrompt : null,
            'caption'      => $generated->caption,
            'story_text'   => $generated->storyText,
            'hashtags'     => $generated->hashtags,
            'call_to_action' => $generated->callToAction,
            'model'        => $generated->model,
            'tokens_used'  => $generated->tokensUsed,
            'raw_response' => $generated->raw,
            'platform'     => $platform,
            'content_type' => $contentType,
            'seo_focus'    => $seoFocus,
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

    private function systemPrompt(BusinessProfile $profile, string $platform = 'instagram', string $contentType = 'post'): string
    {
        $templateKey = "{$platform}_{$contentType}_generator";
        $template = $this->prompts->activeTemplate($templateKey)
            ?? $this->prompts->activeTemplate('instagram_post_generator');

        if ($template !== null) {
            return $template->template;
        }

        $platformInstructions = match ($platform) {
            'tiktok'   => 'Write in TikTok style: hook in first line, short punchy sentences, trending tone. Use 3-5 hashtags max.',
            'facebook' => 'Write for Facebook: slightly longer, conversational, encourage comments and shares. Up to 15 hashtags.',
            'linkedin' => 'Write for LinkedIn: professional B2B tone, focus on value and expertise. Max 5 hashtags.',
            default    => 'Write for Instagram: engaging, authentic, local. 8-15 hashtags.',
        };

        $contentInstructions = match ($contentType) {
            'reel'  => 'This is for a short video Reel (15-30 seconds). story_text should be a punchy video hook/opening line.',
            'story' => 'This is for a Story. story_text should be a short overlay text for a vertical image.',
            'video' => 'This is for a video post. caption should describe what viewers will see.',
            default => 'This is for a standard feed post.',
        };

        return <<<PROMPT
        You are an expert German social media copywriter for local businesses.
        {$platformInstructions}
        {$contentInstructions}
        Always write in German (Deutsch) unless instructed otherwise.
        Respect the brand's tone of voice. Be authentic, not corporate.

        Respond ONLY with a valid JSON object:
        {
          "caption": "Main post caption with emojis (2-4 sentences)",
          "story_text": "Short punchy overlay text for Story/Reel",
          "hashtags": ["array", "of", "relevant", "hashtags", "without spaces"],
          "call_to_action": "One clear CTA"
        }
        Do not include any text outside the JSON object.
        PROMPT;
    }

    private function userPrompt(BusinessProfile $profile, string $userPrompt, ?string $seoFocus = null): string
    {
        $lines = [
            'Business name: '    . $profile->business_name,
            'Business type: '    . ($profile->business_type ?: 'n/a'),
            'City: '             . ($profile->city ?: 'n/a'),
            'Tone of voice: '    . ($profile->tone_of_voice ?: 'freundlich und einladend'),
            'Description: '      . ($profile->description ?: 'n/a'),
            'Products / services: ' . ($profile->products_services ?: 'n/a'),
        ];

        if ($profile->target_audience) {
            $lines[] = 'Target audience: ' . $profile->target_audience;
        }
        if ($profile->unique_value_proposition) {
            $lines[] = 'Unique value: ' . $profile->unique_value_proposition;
        }
        if ($profile->primary_goal) {
            $lines[] = 'Primary goal: ' . $profile->primary_goal;
        }

        if ($seoFocus) {
            $lines[] = "SEO focus keywords to naturally include in caption: {$seoFocus}";
            $lines[] = "Also add location-based hashtags related to: {$seoFocus}";
        } elseif ($profile->city && $profile->business_type) {
            $lines[] = "Naturally mention the city ({$profile->city}) in caption to help local SEO.";
            $lines[] = 'Include at least 2 location hashtags like #' . $profile->city . ' or #' . $profile->city . ucfirst(strtolower($profile->business_type ?? '')) . '.';
        }

        if ($userPrompt !== '') {
            $lines[] = 'Specific request for this post: ' . $userPrompt;
        }

        $lines[] = 'Generate one social media post in German.';

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
