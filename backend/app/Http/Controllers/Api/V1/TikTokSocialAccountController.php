<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use App\Services\SocialProviders\TikTok\Exceptions\TikTokOAuthException;
use App\Services\SocialProviders\TikTok\TikTokOAuthService;
use App\Services\SocialProviders\TikTok\TikTokProviderSettingsService;
use App\Services\SocialProviders\TikTok\TikTokPublishingService;
use App\Services\SocialProviders\TikTok\TikTokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class TikTokSocialAccountController extends Controller
{
    public function __construct(
        private readonly TikTokService $tiktokService,
        private readonly TikTokOAuthService $oauth,
        private readonly TikTokProviderSettingsService $providerSettings,
        private readonly TikTokPublishingService $publishing,
        private readonly AuthorizationService $authorization,
    ) {}

    #[OA\Get(
        path: '/social-accounts/tiktok/connect',
        summary: 'Start TikTok Login',
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
            return ApiResponse::error('TikTok connection is not enabled.', 503);
        }

        try {
            $url = $this->tiktokService->connectUrl($workspace, $request->user());

            return ApiResponse::success(['authorization_url' => $url]);
        } catch (TikTokOAuthException $e) {
            Log::error('TikTok connect failed', ['message' => $e->getMessage()]);

            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    #[OA\Get(
        path: '/social-accounts/tiktok/callback',
        summary: 'TikTok OAuth callback',
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
            Log::warning('TikTok OAuth denied', ['error' => $request->query('error')]);

            return redirect($this->oauth->frontendRedirectUrl(
                'error',
                TikTokOAuthException::userDenied()->getMessage(),
            ));
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $this->tiktokService->handleCallback(
                $validated['code'],
                $validated['state'],
            );

            return redirect($this->oauth->frontendRedirectUrl('connected'));
        } catch (TikTokOAuthException $e) {
            Log::error('TikTok callback failed', ['message' => $e->getMessage()]);

            return redirect($this->oauth->frontendRedirectUrl('error', $e->getMessage()));
        }
    }

    #[OA\Post(
        path: '/social-accounts/tiktok/disconnect',
        summary: 'Disconnect TikTok account',
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
        $account = $this->tiktokService->findTikTokAccount($workspace);

        if ($account !== null) {
            Gate::authorize('disconnect', $account);
        } else {
            Gate::authorize('connect', [SocialAccount::class, $workspace]);
        }

        $this->tiktokService->disconnect($workspace);

        return ApiResponse::success(null, 'TikTok account disconnected.');
    }

    #[OA\Get(
        path: '/social-accounts/tiktok/status',
        summary: 'TikTok connection status',
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

        $dto = $this->tiktokService->status($workspace);

        return ApiResponse::success([
            'connected' => $dto !== null && $dto->status->value === 'connected',
            'account' => $dto?->toArray(),
        ]);
    }

    #[OA\Get(
        path: '/social-accounts/tiktok/creator-info',
        summary: 'TikTok creator info (privacy options for the post UI)',
        security: [['sanctum' => []]],
        tags: ['Social Accounts'],
        parameters: [
            new OA\Parameter(name: 'workspace_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Creator info + audit flag'),
        ],
    )]
    public function creatorInfo(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        Gate::authorize('viewStatus', [SocialAccount::class, $workspace]);

        $account = $this->tiktokService->findTikTokAccount($workspace);

        if ($account === null || $account->status->value !== 'connected') {
            return ApiResponse::error('No connected TikTok account found.', 404);
        }

        $audited = (bool) config('tiktok.audited', false);

        // While unaudited, posts are forced to SELF_ONLY — surface only that
        // option so the UI cannot offer a privacy level TikTok will reject.
        if (! $audited) {
            return ApiResponse::success([
                'audited' => false,
                'creator_info' => [
                    'privacy_level_options' => [TikTokPublishingService::PRIVACY_SELF_ONLY],
                    'comment_disabled' => false,
                    'duet_disabled' => false,
                    'stitch_disabled' => false,
                ],
            ]);
        }

        try {
            $info = $this->publishing->queryCreatorInfo($account);

            return ApiResponse::success([
                'audited' => true,
                'creator_info' => $info->toArray(),
            ]);
        } catch (SocialProviderException $e) {
            Log::error('TikTok creator info failed', ['message' => $e->getMessage()]);

            return ApiResponse::error($e->getMessage(), 422);
        }
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
