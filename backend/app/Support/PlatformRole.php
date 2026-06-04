<?php

namespace App\Support;

final class PlatformRole
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const SUPPORT = 'support';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN, self::SUPPORT];
    }

    /** @return array<string, list<string>> */
    public static function permissionMap(): array
    {
        $all = Permission::platformPermissions();

        return [
            self::SUPER_ADMIN => $all,
            self::ADMIN => [
                Permission::MANAGE_USERS,
                Permission::MANAGE_PLANS,
                Permission::MANAGE_SUBSCRIPTIONS,
                Permission::MANAGE_AI_PROMPTS,
                Permission::MANAGE_SOCIAL_PROVIDERS,
                Permission::VIEW_ADMIN_DASHBOARD,
                Permission::VIEW_USAGE_ANALYTICS,
            ],
            self::SUPPORT => [
                Permission::MANAGE_USERS,
                Permission::VIEW_ADMIN_DASHBOARD,
                Permission::VIEW_USAGE_ANALYTICS,
            ],
        ];
    }
}
