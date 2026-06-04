<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\Workspace\WorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetWorkspaceTeam
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly WorkspaceService $workspaceService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $this->resolveWorkspaceId($request);

        if ($workspaceId === null) {
            return $next($request);
        }

        $user = $request->user();
        $workspace = Workspace::query()->find($workspaceId);

        if ($workspace === null) {
            abort(404, 'Workspace not found.');
        }

        if ($this->workspaceService->membership($user, $workspace) === null) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorization->setWorkspaceTeam($workspace->id);
        $request->attributes->set('workspace', $workspace);

        try {
            return $next($request);
        } finally {
            $this->authorization->clearWorkspaceTeam();
        }
    }

    private function resolveWorkspaceId(Request $request): ?int
    {
        $header = $request->header('X-Workspace-Id');
        if ($header !== null && $header !== '') {
            return (int) $header;
        }

        $query = $request->query('workspace_id') ?? $request->input('workspace_id');

        return $query !== null && $query !== '' ? (int) $query : null;
    }
}
