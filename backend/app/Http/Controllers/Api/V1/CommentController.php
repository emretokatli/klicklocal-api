<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'platform'     => ['nullable', 'string', 'in:instagram,tiktok,facebook,linkedin'],
            'sentiment'    => ['nullable', 'string', 'in:positive,neutral,negative'],
            'limit'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $query = Comment::query()
            ->where('workspace_id', $workspace->id)
            ->latest('commented_at');

        if (!empty($validated['platform'])) {
            $query->where('platform', $validated['platform']);
        }
        if (!empty($validated['sentiment'])) {
            $query->where('sentiment', $validated['sentiment']);
        }

        $comments = $query->limit($validated['limit'] ?? 50)->get();

        return ApiResponse::success(['comments' => $comments]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'platform'     => ['required', 'string', 'in:instagram,tiktok,facebook,linkedin'],
            'author'       => ['required', 'string', 'max:255'],
            'text'         => ['required', 'string', 'max:2000'],
            'sentiment'    => ['nullable', 'string', 'in:positive,neutral,negative'],
            'commented_at' => ['nullable', 'date'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $comment = Comment::create([
            'workspace_id' => $workspace->id,
            'platform'     => $validated['platform'],
            'author'       => $validated['author'],
            'text'         => $validated['text'],
            'sentiment'    => $validated['sentiment'] ?? 'neutral',
            'commented_at' => $validated['commented_at'] ?? now(),
        ]);

        return ApiResponse::success(['comment' => $comment], 'Comment created.', 201);
    }
}
