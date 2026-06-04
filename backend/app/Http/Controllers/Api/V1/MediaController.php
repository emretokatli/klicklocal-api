<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
        ]);

        $items = $this->mediaService
            ->list($request->user(), (int) $request->query('workspace_id'))
            ->map(fn ($media) => [
                'media' => $media,
                'url' => $this->mediaService->url($media),
            ])
            ->values();

        return ApiResponse::success(['items' => $items]);
    }

    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $media = $this->mediaService->upload(
            $request->user(),
            (int) $request->validated('workspace_id'),
            $request->file('file'),
        );

        return ApiResponse::success([
            'media' => $media,
            'url' => $this->mediaService->url($media),
        ], 'Media uploaded successfully.', 201);
    }
}
