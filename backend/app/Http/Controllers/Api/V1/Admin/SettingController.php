<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    private const CACHE_KEY = 'platform.settings';

    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorization->assertPlatformPermission(
            $request->user(),
            Permission::MANAGE_PLATFORM_SETTINGS,
        );

        return ApiResponse::success([
            'settings' => Cache::get(self::CACHE_KEY, $this->defaults()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorization->assertPlatformPermission(
            $request->user(),
            Permission::MANAGE_PLATFORM_SETTINGS,
        );

        $validated = $request->validate([
            'app_name' => ['sometimes', 'string', 'max:255'],
            'support_email' => ['sometimes', 'email'],
            'default_timezone' => ['sometimes', 'timezone'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
        ]);

        $settings = array_merge($this->defaults(), Cache::get(self::CACHE_KEY, []), $validated);
        Cache::forever(self::CACHE_KEY, $settings);

        return ApiResponse::success(['settings' => $settings], 'Settings updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'app_name' => config('app.name'),
            'support_email' => config('mail.from.address'),
            'default_timezone' => 'UTC',
            'maintenance_mode' => false,
            'trial_days' => 14,
        ];
    }
}
