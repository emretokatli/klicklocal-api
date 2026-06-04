<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Post\PublishPostNowAction;
use App\Actions\Post\SchedulePostAction;
use App\Http\Requests\Post\PublishPostRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\SchedulePostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Post;
use App\Services\Post\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $postService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
        ]);

        $posts = $this->postService->list(
            $request->user(),
            (int) $request->query('workspace_id'),
        );

        return ApiResponse::success(['posts' => $posts]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $validated = $request->validated();
        $post = $this->postService->create(
            $request->user(),
            (int) $validated['workspace_id'],
            $validated,
        );

        return ApiResponse::success(
            ['post' => $post],
            'Post created successfully.',
            201,
        );
    }

    public function show(Request $request, int $post): JsonResponse
    {
        $model = $this->postService->find($request->user(), $post);
        $this->authorize('view', $model);

        return ApiResponse::success(['post' => $model]);
    }

    public function update(UpdatePostRequest $request, int $post): JsonResponse
    {
        $model = $this->postService->find($request->user(), $post);
        $this->authorize('update', $model);

        $updated = $this->postService->update(
            $request->user(),
            $model,
            $request->validated(),
        );

        return ApiResponse::success(
            ['post' => $updated],
            'Post updated successfully.',
        );
    }

    public function destroy(Request $request, int $post): JsonResponse
    {
        $model = $this->postService->find($request->user(), $post);
        $this->authorize('delete', $model);

        $this->postService->delete($request->user(), $model);

        return ApiResponse::success(null, 'Post deleted successfully.');
    }

    public function schedule(
        SchedulePostRequest $request,
        int $post,
        SchedulePostAction $schedulePost,
    ): JsonResponse {
        $model = $this->postService->find($request->user(), $post);
        $this->authorize('schedule', $model);

        $scheduled = $schedulePost->execute(
            $request->user(),
            $model,
            $request->validated(),
        );

        return ApiResponse::success(
            ['post' => $scheduled],
            'Post scheduled successfully.',
        );
    }

    public function publish(
        PublishPostRequest $request,
        int $post,
        PublishPostNowAction $publishPost,
    ): JsonResponse {
        $model = $this->postService->find($request->user(), $post);
        $this->authorize('schedule', $model);

        $published = $publishPost->execute(
            $request->user(),
            $model,
            $request->validated(),
        );

        return ApiResponse::success(
            ['post' => $published],
            'Post queued for publishing.',
        );
    }
}
