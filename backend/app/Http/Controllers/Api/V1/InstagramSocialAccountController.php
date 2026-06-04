<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\SocialProviders\Instagram\Exceptions\InstagramOAuthException;
use App\Services\SocialProviders\Instagram\InstagramOAuthService;
use App\Services\SocialProviders\Instagram\InstagramProviderSettingsService;
use App\Services\SocialProviders\Instagram\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class InstagramSocialAccountController extends Controller
{
    public function __construct(
        private readonly InstagramService $instagramService,
        private readonly InstagramOAuthService $oauth,
        private readonly InstagramProviderSettingsService $providerSettings,
        private readonly AuthorizationService $authorization,
    ) {}

    #[OA\Get(
        path: '/social-accounts/instagram/connect',
        summary: 'Start Instagram Business Login',
        security: [['sanctum' => []]],
        tags: ['Social Accounts'],
        parameters: [
            new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authorization URL',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'authorization_url', type: 'string'),
                    ],
                ),
            ),
        ],
    )]
    public function connect(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        Gate::authorize('connect', [SocialAccount::class, $workspace]);

        if (! $this->providerSettings->isEnabled()) {
            return ApiResponse::error('Instagram connection is not enabled.', 503);
        }

        try {
            $url = $this->instagramService->connectUrl($workspace, $request->user());

            return ApiResponse::success(['authorization_url' => $url]);
        } catch (InstagramOAuthException $e) {
            Log::error('Instagram connect failed', ['message' => $e->getMessage()]);

            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    #[OA\Get(
        path: '/social-accounts/instagram/callback',
        summary: 'Instagram OAuth callback',
        tags: ['Social Accounts'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'error', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to frontend'),
        ],
    )]
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::warning('Instagram OAuth denied', ['error' => $request->query('error')]);

            return redirect($this->oauth->frontendRedirectUrl(
                'error',
                InstagramOAuthException::userDenied()->getMessage(),
            ));
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $this->instagramService->handleCallback(
                $validated['code'],
                $validated['state'],
            );

            return redirect($this->oauth->frontendRedirectUrl('connected'));
        } catch (InstagramOAuthException $e) {
            Log::error('Instagram callback failed', ['message' => $e->getMessage()]);

            return redirect($this->oauth->frontendRedirectUrl('error', $e->getMessage()));
        }
    }

    #[OA\Post(
        path: '/social-accounts/instagram/disconnect',
        summary: 'Disconnect Instagram account',
        security: [['sanctum' => []]],
        tags: ['Social Accounts'],
        parameters: [
            new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Disconnected'),
        ],
    )]
    public function disconnect(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $account = $this->instagramService->findInstagramAccount($workspace);

        if ($account !== null) {
            Gate::authorize('disconnect', $account);
        } else {
            Gate::authorize('connect', [SocialAccount::class, $workspace]);
        }

        $this->instagramService->disconnect($workspace);

        return ApiResponse::success(null, 'Instagram account disconnected.');
    }

    #[OA\Get(
        path: '/social-accounts/instagram/status',
        summary: 'Instagram connection status',
        security: [['sanctum' => []]],
        tags: ['Social Accounts'],
        parameters: [
            new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status'),
        ],
    )]
    public function status(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        Gate::authorize('viewStatus', [SocialAccount::class, $workspace]);

        $dto = $this->instagramService->status($workspace);

        return ApiResponse::success([
            'connected' => $dto !== null && $dto->status->value === 'connected',
            'account' => $dto?->toArray(),
        ]);
    }

    private function resolveWorkspace(Request $request): Workspace
    {
        $workspaceId = $request->query('workspace_id') ?? $request->input('workspace_id');

        if ($workspaceId === null) {
            abort(422, 'workspace_id is required.');
        }

        return Workspace::query()->findOrFail((int) $workspaceId);
    }
}
