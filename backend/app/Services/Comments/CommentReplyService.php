<?php

namespace App\Services\Comments;

use App\Models\BusinessProfile;
use App\Models\Comment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Validation\ValidationException;

class CommentReplyService
{
    public function __construct(
        private readonly OpenAiClientInterface $client,
        private readonly UsageTrackingService $usage,
    ) {}

    /**
     * Generate (or regenerate) an AI reply suggestion for one comment and
     * persist it on `suggested_reply`. The business profile is optional
     * context — suggestion must keep working before onboarding is complete.
     */
    public function suggest(User $user, Workspace $workspace, Comment $comment): Comment
    {
        $profile = $workspace->businessProfile;

        $dto = $this->client->suggestCommentReply(
            $this->systemPrompt(),
            $this->userPrompt($profile, $comment),
            $this->context($profile, $comment),
        );

        $comment->forceFill(['suggested_reply' => $dto->replyText])->save();

        $this->usage->recordAi(
            $user,
            $workspace,
            'comment_reply_suggestion',
            $dto->tokensUsed > 0 ? $dto->tokensUsed : 1,
        );

        return $comment;
    }

    /**
     * Store the reply on the comment. Only synced comments (with a provider
     * external_id) can be answered — manual comments have no platform thread
     * to deliver the reply to.
     */
    public function reply(Comment $comment, string $replyText): Comment
    {
        if ($comment->external_id === null) {
            throw ValidationException::withMessages([
                'comment' => ['Manually created comments cannot be replied to — there is no platform comment to answer.'],
            ]);
        }

        if ($comment->replied_at !== null) {
            throw ValidationException::withMessages([
                'comment' => ['This comment has already been replied to.'],
            ]);
        }

        $comment->forceFill([
            'reply_text' => $replyText,
            'replied_at' => now(),
        ])->save();

        return $comment;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You write short replies to social media comments on behalf of a local German business.
        The reply is posted publicly under the comment, so keep it warm, personal and brief
        (1-3 sentences, German "du" form, at most one emoji). Thank for praise, de-escalate
        and offer a direct contact for complaints, answer questions if the business context
        allows it — otherwise invite the person to send a DM. Never invent facts, prices or
        opening hours. Always answer in German.

        Respond ONLY with a valid JSON object of this exact shape:
        {"reply": "<the reply text>"}
        Do not include any text outside the JSON object.
        PROMPT;
    }

    private function userPrompt(?BusinessProfile $profile, Comment $comment): string
    {
        $lines = [];

        if ($profile !== null) {
            $lines[] = 'Business name: '.$profile->business_name;
            $lines[] = 'Business type: '.($profile->business_type ?: 'n/a');
            $lines[] = 'Tone of voice: '.($profile->tone_of_voice ?: 'freundlich und einladend');

            if ($profile->description) {
                $lines[] = 'Description: '.$profile->description;
            }
        }

        $lines[] = 'Platform: '.$comment->platform;
        $lines[] = 'Comment sentiment: '.$comment->sentiment;
        $lines[] = 'Comment from @'.$comment->author.': "'.$comment->text.'"';
        $lines[] = 'Write one public reply to this comment in German.';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private function context(?BusinessProfile $profile, Comment $comment): array
    {
        return [
            'business_name' => (string) ($profile?->business_name ?? ''),
            'business_type' => (string) ($profile?->business_type ?? ''),
            'comment_text' => (string) $comment->text,
            'comment_sentiment' => (string) $comment->sentiment,
        ];
    }
}
