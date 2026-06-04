<?php

namespace App\Enums;

use App\Support\WorkspaceRoleName;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function spatieName(): string
    {
        return $this->value;
    }

    public function canManageWorkspace(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canEditContent(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Editor], true);
    }

    public static function fromSpatie(string $name): self
    {
        return match ($name) {
            WorkspaceRoleName::OWNER => self::Owner,
            WorkspaceRoleName::MANAGER => self::Manager,
            WorkspaceRoleName::EDITOR => self::Editor,
            WorkspaceRoleName::VIEWER => self::Viewer,
            default => self::Viewer,
        };
    }
}
