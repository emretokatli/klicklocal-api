<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\GenerateContentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\AiGeneration;
use App\Services\Ai\AiContentGenerationService;
use App\Services\Ai\ImageGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiContentController extends Controller
{
    public function __construct(
        private readonly AiContentGenerationService $generator,
        private readonly ImageGenerationService $imageGenerator,
    ) {}

    public function generate(GenerateContentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $generation = $this->generator->generate(
            $request->user(),
            (int) $validated['workspace_id'],
            [
                'media_id'     => isset($validated['media_id']) ? (int) $validated['media_id'] : null,
                'prompt'       => $validated['prompt'] ?? null,
                'platform'     => $validated['platform'] ?? 'instagram',    // ← ekle
                'content_type' => $validated['content_type'] ?? 'post',     // ← ekle
                'seo_focus'    => $validated['seo_focus'] ?? null,          // ← ekle
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

    public function generateImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id'  => ['required', 'integer', 'exists:workspaces,id'],
            'prompt'        => ['nullable', 'string', 'max:500'],
            'platform'      => ['nullable', 'string', 'in:instagram,facebook,tiktok,linkedin'],
            'content_type'  => ['nullable', 'string', 'in:post,reel,story,video'],
            'generation_id' => ['nullable', 'integer', 'exists:ai_generations,id'],
        ]);

        $dto = $this->imageGenerator->generate(
            user: $request->user(),
            workspaceId: (int) $validated['workspace_id'],
            userPrompt: $validated['prompt'] ?? '',
            platform: $validated['platform'] ?? 'instagram',
            contentType: $validated['content_type'] ?? 'post',
        );

        if (isset($validated['generation_id'])) {
            AiGeneration::query()
                ->where('id', $validated['generation_id'])
                ->where('workspace_id', $validated['workspace_id'])
                ->update([
                    'generated_image_url'  => $dto->imageUrl,
                    'image_model'          => $dto->model,
                    'image_revised_prompt' => $dto->revisedPrompt,
                ]);
        }

        return ApiResponse::success([
            'image_url'      => $dto->imageUrl,
            'model'          => $dto->model,
            'revised_prompt' => $dto->revisedPrompt,
        ], 'Image generated.', 201);
    }
}
