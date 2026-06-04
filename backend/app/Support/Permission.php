<?php

namespace App\Support;

/**
 * Central permission names for Spatie Laravel Permission.
 */
final class Permission
{
    public const GUARD = 'web';

    // Platform (admin dashboard)
    public const MANAGE_USERS = 'manage_users';
    public const MANAGE_PLANS = 'manage_plans';
    public const MANAGE_SUBSCRIPTIONS = 'manage_subscriptions';
    public const MANAGE_AI_PROMPTS = 'manage_ai_prompts';
    public const MANAGE_SOCIAL_PROVIDERS = 'manage_social_providers';
    public const MANAGE_PLATFORM_SETTINGS = 'manage_platform_settings';
    public const VIEW_ADMIN_DASHBOARD = 'view_admin_dashboard';
    public const VIEW_USAGE_ANALYTICS = 'view_usage_analytics';

    // Workspace (team-scoped)
    public const MANAGE_WORKSPACE = 'manage_workspace';
    public const MANAGE_MEMBERS = 'manage_members';
    public const VIEW_WORKSPACE = 'view_workspace';
    public const CREATE_POSTS = 'create_posts';
    public const EDIT_POSTS = 'edit_posts';
    public const DELETE_POSTS = 'delete_posts';
    public const SCHEDULE_POSTS = 'schedule_posts';
    public const VIEW_POSTS = 'view_posts';
    public const UPLOAD_MEDIA = 'upload_media';
    public const VIEW_MEDIA = 'view_media';
    public const MANAGE_SOCIAL_ACCOUNTS = 'manage_social_accounts';

    /** @return list<string> */
    public static function platformPermissions(): array
    {
        return [
            self::MANAGE_USERS,
            self::MANAGE_PLANS,
            self::MANAGE_SUBSCRIPTIONS,
            self::MANAGE_AI_PROMPTS,
            self::MANAGE_SOCIAL_PROVIDERS,
            self::MANAGE_PLATFORM_SETTINGS,
            self::VIEW_ADMIN_DASHBOARD,
            self::VIEW_USAGE_ANALYTICS,
        ];
    }

    /** @return list<string> */
    public static function workspacePermissions(): array
    {
        return [
            self::MANAGE_WORKSPACE,
            self::MANAGE_MEMBERS,
            self::VIEW_WORKSPACE,
            self::CREATE_POSTS,
            self::EDIT_POSTS,
            self::DELETE_POSTS,
            self::SCHEDULE_POSTS,
            self::VIEW_POSTS,
            self::UPLOAD_MEDIA,
            self::VIEW_MEDIA,
            self::MANAGE_SOCIAL_ACCOUNTS,
        ];
    }

    /** @return array<string, list<string>> */
    public static function workspaceRolePermissionMap(): array
    {
        return [
            WorkspaceRoleName::OWNER => self::workspacePermissions(),
            WorkspaceRoleName::MANAGER => [
                self::MANAGE_WORKSPACE,
                self::MANAGE_MEMBERS,
                self::VIEW_WORKSPACE,
                self::CREATE_POSTS,
                self::EDIT_POSTS,
                self::DELETE_POSTS,
                self::SCHEDULE_POSTS,
                self::VIEW_POSTS,
                self::UPLOAD_MEDIA,
                self::VIEW_MEDIA,
                self::MANAGE_SOCIAL_ACCOUNTS,
            ],
            WorkspaceRoleName::EDITOR => [
                self::VIEW_WORKSPACE,
                self::CREATE_POSTS,
                self::EDIT_POSTS,
                self::SCHEDULE_POSTS,
                self::VIEW_POSTS,
                self::UPLOAD_MEDIA,
                self::VIEW_MEDIA,
            ],
            WorkspaceRoleName::VIEWER => [
                self::VIEW_WORKSPACE,
                self::VIEW_POSTS,
                self::VIEW_MEDIA,
            ],
        ];
    }
}
