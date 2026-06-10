<?php

namespace App\Events;

use App\Models\Workspace;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a comment sync stores new comments for a workspace.
 *
 * Extension point for the upcoming sentiment classifier: register a listener
 * that loads the comments by id and fills the `sentiment` column.
 */
class CommentsIngested
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<int>  $commentIds  Ids of newly created comments (no updates).
     */
    public function __construct(
        public Workspace $workspace,
        public array $commentIds,
    ) {}
}
