<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use App\Services\Comments\CommentReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentReplyService $replies,
    ) {}

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
            // An explicitly provided sentiment counts as classified so the AI
            // sweep won't overwrite it; otherwise leave NULL for the classifier.
            'sentiment_classified_at' => isset($validated['sentiment']) ? now() : null,
            'commented_at' => $validated['commented_at'] ?? now(),
        ]);

        return ApiResponse::success(['comment' => $comment], 'Comment created.', 201);
    }

    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'platform'     => ['nullable', 'string', 'in:instagram,tiktok,facebook,linkedin'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $query = Comment::query()->where('workspace_id', $workspace->id);

        if (!empty($validated['platform'])) {
            $query->where('platform', $validated['platform']);
        }

        $counts = $query
            ->selectRaw('sentiment, COUNT(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        $positive = (int) ($counts['positive'] ?? 0);
        $neutral = (int) ($counts['neutral'] ?? 0);
        $negative = (int) ($counts['negative'] ?? 0);

        return ApiResponse::success([
            'stats' => [
                'total' => $positive + $neutral + $negative,
                'positive' => $positive,
                'neutral' => $neutral,
                'negative' => $negative,
            ],
        ]);
    }

    public function suggestReply(Request $request, Comment $comment): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        abort_unless($comment->workspace_id === $workspace->id, 404);

        $comment = $this->replies->suggest($request->user(), $workspace, $comment);

        return ApiResponse::success(['comment' => $comment], 'Reply suggestion generated.');
    }

    public function reply(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'reply_text' => ['required', 'string', 'max:2000'],
        ]);

        $workspace = $request->attributes->get('workspace');

        abort_unless($comment->workspace_id === $workspace->id, 404);

        $comment = $this->replies->reply($comment, $validated['reply_text']);

        return ApiResponse::success(['comment' => $comment], 'Reply sent.');
    }
}
