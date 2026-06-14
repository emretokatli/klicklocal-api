<?php

namespace App\Listeners;

use App\Events\CommentsIngested;
use App\Jobs\ClassifyCommentsJob;

/**
 * Hooks the sentiment classifier into the comment ingest flow (T7 extension
 * point). Only dispatches a queued job — classification can never block or
 * fail ingestion itself.
 */
class ClassifyIngestedComments
{
    public function handle(CommentsIngested $event): void
    {
        ClassifyCommentsJob::dispatch($event->workspace, $event->commentIds);
    }
}
