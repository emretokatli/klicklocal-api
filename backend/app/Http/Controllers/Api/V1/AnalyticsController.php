<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function kpi(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $publishedCount = Post::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'published')
            ->count();

        // Simulated KPIs — real platform API data in a later phase
        $kpi = [
            'impressions'     => $publishedCount * 420,
            'reach'           => (int) ($publishedCount * 310),
            'engagement_rate' => $publishedCount > 0 ? 5.8 : 0.0,
            'published_posts' => $publishedCount,
            'is_estimated'    => true,
        ];

        return ApiResponse::success(['kpi' => $kpi]);
    }
}
