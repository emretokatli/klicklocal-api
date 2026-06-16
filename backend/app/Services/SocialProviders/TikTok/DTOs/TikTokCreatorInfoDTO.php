<?php

namespace App\Services\SocialProviders\TikTok\DTOs;

readonly class TikTokCreatorInfoDTO
{
    /**
     * @param  list<string>  $privacyLevelOptions
     */
    public function __construct(
        public array $privacyLevelOptions,
        public bool $commentDisabled = false,
        public bool $duetDisabled = false,
        public bool $stitchDisabled = false,
        public ?int $maxVideoPostDurationSec = null,
        public ?string $creatorUsername = null,
        public ?string $creatorNickname = null,
        public ?string $creatorAvatarUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $data = $payload['data'] ?? $payload;

        $options = [];
        if (isset($data['privacy_level_options']) && is_array($data['privacy_level_options'])) {
            $options = array_values(array_map('strval', $data['privacy_level_options']));
        }

        return new self(
            privacyLevelOptions: $options,
            commentDisabled: (bool) ($data['comment_disabled'] ?? false),
            duetDisabled: (bool) ($data['duet_disabled'] ?? false),
            stitchDisabled: (bool) ($data['stitch_disabled'] ?? false),
            maxVideoPostDurationSec: isset($data['max_video_post_duration_sec'])
                ? (int) $data['max_video_post_duration_sec']
                : null,
            creatorUsername: isset($data['creator_username']) ? (string) $data['creator_username'] : null,
            creatorNickname: isset($data['creator_nickname']) ? (string) $data['creator_nickname'] : null,
            creatorAvatarUrl: isset($data['creator_avatar_url']) ? (string) $data['creator_avatar_url'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'privacy_level_options' => $this->privacyLevelOptions,
            'comment_disabled' => $this->commentDisabled,
            'duet_disabled' => $this->duetDisabled,
            'stitch_disabled' => $this->stitchDisabled,
            'max_video_post_duration_sec' => $this->maxVideoPostDurationSec,
            'creator_username' => $this->creatorUsername,
            'creator_nickname' => $this->creatorNickname,
            'creator_avatar_url' => $this->creatorAvatarUrl,
        ];
    }
}
