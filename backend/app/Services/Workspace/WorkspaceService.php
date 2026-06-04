<?php

namespace App\Services\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\Plan;
use App\Services\Authorization\WorkspaceRoleSyncService;
use App\Services\Billing\WorkspaceSubscriptionService;
use Illuminate\Support\Str;

class WorkspaceService
{
    public function __construct(
        private readonly WorkspaceRoleSyncService $roleSync,
        private readonly WorkspaceSubscriptionService $subscriptions,
    ) {}
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Workspace>
     */
    public function listForUser(User $user)
    {
        return Workspace::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('workspaceMembers', fn ($q) => $q->where('user_id', $user->id))
            ->with(['owner:id,name,email'])
            ->latest()
            ->get();
    }

    /**
     * @param  array{name: string, timezone?: string, logo?: string}  $data
     */
    public function create(User $user, array $data): Workspace
    {
        $slug = $this->uniqueSlug($data['name']);

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => $data['name'],
            'slug' => $slug,
            'logo' => $data['logo'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        $this->roleSync->assignOwner($user, $workspace);

        $defaultPlan = Plan::query()->where('slug', 'starter')->where('is_active', true)->first()
            ?? Plan::query()->where('is_active', true)->orderBy('sort_order')->first();

        if ($defaultPlan !== null) {
            $this->subscriptions->subscribe($workspace, $defaultPlan, 'monthly', actor: $user);
        }

        return $workspace->load('owner:id,name,email');
    }

    public function findForUser(User $user, int $id): Workspace
    {
        return Workspace::query()
            ->where('id', $id)
            ->where(function ($q) use ($user): void {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('workspaceMembers', fn ($m) => $m->where('user_id', $user->id));
            })
            ->with(['owner:id,name,email', 'workspaceMembers.user:id,name,email'])
            ->firstOrFail();
    }

    /**
     * @param  array{name?: string, timezone?: string, logo?: string}  $data
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        if (isset($data['name'])) {
            $workspace->name = $data['name'];
            if (! isset($data['slug'])) {
                $workspace->slug = $this->uniqueSlug($data['name'], $workspace->id);
            }
        }

        if (isset($data['timezone'])) {
            $workspace->timezone = $data['timezone'];
        }

        if (array_key_exists('logo', $data)) {
            $workspace->logo = $data['logo'];
        }

        $workspace->save();

        return $workspace->fresh(['owner:id,name,email']);
    }

    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }

    public function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        if ($workspace->owner_id === $user->id) {
            return WorkspaceMember::make([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);
        }

        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Workspace::query()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
