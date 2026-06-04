<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Authorization\AuthorizationService;
use App\Services\SocialProviders\SocialProviderSettingsRegistry;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SocialProviderController extends Controller
{
    public function __construct(
        private readonly SocialProviderSettingsRegistry $registry,
        private readonly AuthorizationService $authorization,
    ) {}

    #[OA\Get(
        path: '/admin/providers',
        summary: 'List social provider settings',
        security: [['sanctum' => []]],
        tags: ['Admin Providers'],
        responses: [new OA\Response(response: 200, description: 'Provider list')],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorization->assertPlatformPermission(
            $request->user(),
            Permission::MANAGE_SOCIAL_PROVIDERS,
        );

        return ApiResponse::success([
            'providers' => $this->registry->adminViews(),
        ]);
    }

    #[OA\Put(
        path: '/admin/providers/{provider}',
        summary: 'Update social provider settings',
        security: [['sanctum' => []]],
        tags: ['Admin Providers'],
        responses: [new OA\Response(response: 200, description: 'Updated')],
    )]
    public function update(Request $request, string $provider): JsonResponse
    {
        $this->authorization->assertPlatformPermission(
            $request->user(),
            Permission::MANAGE_SOCIAL_PROVIDERS,
        );

        $service = $this->registry->resolve($provider);
        if ($service === null) {
            abort(404, 'Provider not supported.');
        }

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'app_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'callback_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'api_version' => ['sometimes', 'nullable', 'string', 'max:16'],
            'scopes' => ['sometimes', 'nullable', 'array'],
            'scopes.*' => ['string', 'max:128'],
        ]);

        $service->update($validated);

        return ApiResponse::success([
            'provider' => $service->adminView(),
        ], ucfirst($provider).' provider settings updated.');
    }

    #[OA\Put(
        path: '/admin/providers/instagram',
        summary: 'Update Instagram provider settings',
        security: [['sanctum' => []]],
        tags: ['Admin Providers'],
        responses: [new OA\Response(response: 200, description: 'Updated')],
    )]
    public function updateInstagram(Request $request): JsonResponse
    {
        return $this->update($request, 'instagram');
    }
}
