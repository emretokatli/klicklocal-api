<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorization->assertPlatformPermission(
            $request->user(),
            Permission::MANAGE_SUBSCRIPTIONS,
        );

        $workspaces = Workspace::query()
            ->with('owner:id,name,email')
            ->latest()
            ->get();

        return ApiResponse::success(['workspaces' => $workspaces]);
    }
}
