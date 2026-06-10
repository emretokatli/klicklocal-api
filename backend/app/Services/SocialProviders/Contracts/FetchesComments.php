<?php

namespace App\Services\SocialProviders\Contracts;

use App\Models\SocialAccount;
use App\Services\SocialProviders\DTOs\CommentCollectionDTO;

/**
 * Optional provider capability: reading comments on a published media item.
 * Providers that cannot support it simply do not implement this interface;
 * the comment sync skips them (see CommentSyncService).
 */
interface FetchesComments
{
    /**
     * Fetch comments for a provider media/post id (post_platforms.platform_post_id).
     *
     * @param  string|null  $since  ISO-8601 timestamp; only comments newer than this are returned.
     */
    public function fetchComments(
        SocialAccount $account,
        string $providerMediaId,
        ?string $since = null,
    ): CommentCollectionDTO;
}
