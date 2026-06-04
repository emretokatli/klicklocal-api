<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspace;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        $workspaces = $this->workspaceService->listForUser($request->user());

        return ApiResponse::success(['workspaces' => $workspaces]);
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        $workspace = $this->workspaceService->create(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(
            ['workspace' => $workspace],
            'Workspace created successfully.',
            201,
        );
    }

    public function show(Request $request, int $workspace): JsonResponse
    {
        $model = $this->workspaceService->findForUser($request->user(), $workspace);
        $this->authorize('view', $model);

        return ApiResponse::success(['workspace' => $model]);
    }

    public function update(UpdateWorkspaceRequest $request, int $workspace): JsonResponse
    {
        $model = $this->workspaceService->findForUser($request->user(), $workspace);
        $this->authorize('update', $model);

        $updated = $this->workspaceService->update($model, $request->validated());

        return ApiResponse::success(
            ['workspace' => $updated],
            'Workspace updated successfully.',
        );
    }

    public function destroy(Request $request, int $workspace): JsonResponse
    {
        $model = $this->workspaceService->findForUser($request->user(), $workspace);
        $this->authorize('delete', $model);

        $this->workspaceService->delete($model);

        return ApiResponse::success(null, 'Workspace deleted successfully.');
    }
}
