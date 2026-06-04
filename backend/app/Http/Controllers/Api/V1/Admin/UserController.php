<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return ApiResponse::success([
            'users' => $this->users->list(),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success([
            'user' => $this->users->find($user->id),
        ]);
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $this->authorize('updateRoles', User::class);

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'in:super_admin,admin,support'],
        ]);

        $updated = $this->users->syncPlatformRoles($user, $validated['roles']);

        return ApiResponse::success(
            ['user' => $updated],
            'Platform roles updated.',
        );
    }
}
