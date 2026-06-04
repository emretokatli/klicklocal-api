<?php

namespace App\Support;

final class WorkspaceRoleName
{
    public const OWNER = 'owner';
    public const MANAGER = 'manager';
    public const EDITOR = 'editor';
    public const VIEWER = 'viewer';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::OWNER, self::MANAGER, self::EDITOR, self::VIEWER];
    }
}
