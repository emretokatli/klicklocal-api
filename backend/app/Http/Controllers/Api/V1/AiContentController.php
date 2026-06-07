<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\GenerateContentRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Ai\AiContentGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiContentController extends Controller
{
    public function __construct(
        private readonly AiContentGenerationService $generator,
    ) {}

    public function generate(GenerateContentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $generation = $this->generator->generate(
            $request->user(),
            (int) $validated['workspace_id'],
            [
                'media_id' => isset($validated['media_id']) ? (int) $validated['media_id'] : null,
                'prompt' => $validated['prompt'] ?? null,
            ],
        );

        return ApiResponse::success(
            ['generation' => $generation],
            'Content generated successfully.',
            201,
        );
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
        ]);

        $generations = $this->generator->history(
            $request->user(),
            (int) $request->query('workspace_id'),
        );

        return ApiResponse::success(['generations' => $generations]);
    }
}
