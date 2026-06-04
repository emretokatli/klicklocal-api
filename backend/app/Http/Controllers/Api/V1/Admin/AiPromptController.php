<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AiPromptCategory;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AiPromptTemplate;
use App\Services\Ai\AiPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiPromptController extends Controller
{
    public function __construct(
        private readonly AiPromptService $prompts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiPromptTemplate::class);

        $category = $request->query('category');
        $activeOnly = $request->boolean('active_only');

        return ApiResponse::success([
            'prompts' => $this->prompts->list(
                $category ? AiPromptCategory::from($category) : null,
                $activeOnly ? true : null,
            ),
        ]);
    }

    public function show(AiPromptTemplate $aiPromptTemplate): JsonResponse
    {
        $this->authorize('view', $aiPromptTemplate);

        return ApiResponse::success(['prompt' => $aiPromptTemplate]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AiPromptTemplate::class);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:128', 'unique:ai_prompt_templates,key'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(AiPromptCategory::class)],
            'description' => ['nullable', 'string'],
            'template' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $prompt = $this->prompts->create($validated, $request->user());

        return ApiResponse::success(['prompt' => $prompt], 'AI prompt created.', 201);
    }

    public function update(Request $request, AiPromptTemplate $aiPromptTemplate): JsonResponse
    {
        $this->authorize('update', $aiPromptTemplate);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', Rule::enum(AiPromptCategory::class)],
            'description' => ['nullable', 'string'],
            'template' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $prompt = $this->prompts->update($aiPromptTemplate, $validated, $request->user());

        return ApiResponse::success(['prompt' => $prompt], 'AI prompt updated.');
    }

    public function setActive(Request $request, AiPromptTemplate $aiPromptTemplate): JsonResponse
    {
        $this->authorize('update', $aiPromptTemplate);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $prompt = $this->prompts->setActive($aiPromptTemplate, $validated['is_active']);

        return ApiResponse::success(['prompt' => $prompt], 'AI prompt status updated.');
    }
}
