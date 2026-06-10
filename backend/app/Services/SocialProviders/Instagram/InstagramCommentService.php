<?php

namespace App\Services\SocialProviders\Instagram;

use App\Models\SocialAccount;
use App\Services\SocialProviders\DTOs\CommentCollectionDTO;
use App\Services\SocialProviders\DTOs\CommentDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramCommentService
{
    /** Safety cap so a viral post cannot keep the sync paginating forever. */
    private const MAX_PAGES = 10;

    /**
     * Read the comments edge of a published media item.
     *
     * GET {graph}/{version}/{ig-media-id}/comments?fields=id,text,username,timestamp
     * Requires the instagram_business_manage_comments scope.
     */
    public function fetchComments(
        SocialAccount $account,
        string $providerMediaId,
        ?string $since = null,
    ): CommentCollectionDTO {
        $version = config('instagram.api_version', 'v21.0');
        $base = rtrim((string) config('instagram.graph_base_url'), '/');
        $sinceAt = $since !== null ? Carbon::parse($since) : null;

        $url = "{$base}/{$version}/{$providerMediaId}/comments";
        $query = [
            'fields' => 'id,text,username,timestamp',
            'limit' => 50,
            'access_token' => (string) $account->access_token,
        ];

        $comments = [];
        $pages = 0;

        while ($url !== null && $pages < self::MAX_PAGES) {
            try {
                $response = Http::timeout(30)->get($url, $query);
            } catch (RequestException $e) {
                throw SocialProviderException::networkError('instagram', $e->getMessage());
            }

            if (! $response->successful()) {
                $message = $this->extractGraphError($response->json(), $response->body());

                Log::warning('Instagram comments fetch failed', [
                    'social_account_id' => $account->id,
                    'provider_media_id' => $providerMediaId,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                // Expired tokens / missing permissions surface here (OAuthException).
                // Throw so CommentSyncService can log and skip the account.
                throw SocialProviderException::invalidAccount('instagram', $message);
            }

            foreach ((array) $response->json('data', []) as $item) {
                $externalId = (string) ($item['id'] ?? '');

                if ($externalId === '') {
                    continue;
                }

                $commentedAt = isset($item['timestamp'])
                    ? Carbon::parse((string) $item['timestamp'])
                    : null;

                if ($sinceAt !== null && $commentedAt !== null && $commentedAt->lessThanOrEqualTo($sinceAt)) {
                    continue;
                }

                $comments[] = new CommentDTO(
                    externalId: $externalId,
                    author: (string) ($item['username'] ?? 'instagram_user'),
                    text: (string) ($item['text'] ?? ''),
                    commentedAt: $commentedAt,
                    raw: is_array($item) ? $item : null,
                );
            }

            // Cursor pagination: paging.next is a fully-qualified URL.
            $url = $response->json('paging.next');
            $query = [];
            $pages++;
        }

        return new CommentCollectionDTO($comments);
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractGraphError(?array $json, string $fallbackBody): string
    {
        if (isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }

        return Str::limit($fallbackBody, 500);
    }
}
