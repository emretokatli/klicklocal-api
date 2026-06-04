<?php

namespace App\Contracts\Post;

use App\Models\Post;

interface PostPublisherInterface
{
    /**
     * Publish a post to configured social platforms (or simulate when none).
     */
    public function publish(Post $post): void;
}
