<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessProfile\UpdateBusinessProfileRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Business\BusinessProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessProfileController extends Controller
{
    public function __construct(
        private readonly BusinessProfileService $profiles,
    ) {}

    public function show(Request $request, int $workspace): JsonResponse
    {
        $profile = $this->profiles->show($request->user(), $workspace);

        return ApiResponse::success(['business_profile' => $profile]);
    }

    public function update(UpdateBusinessProfileRequest $request, int $workspace): JsonResponse
    {
        $profile = $this->profiles->upsert(
            $request->user(),
            $workspace,
            $request->validated(),
        );

        return ApiResponse::success(
            ['business_profile' => $profile],
            'Business profile saved.',
        );
    }
}
