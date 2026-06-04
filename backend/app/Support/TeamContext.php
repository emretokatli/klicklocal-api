<?php

namespace App\Support;

/**
 * Spatie Permission team IDs. Platform-scoped roles use 0 (not null) because
 * pivot tables include workspace_id in the primary key on MySQL.
 */
final class TeamContext
{
    public const PLATFORM = 0;
}
