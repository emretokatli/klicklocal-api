<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyzeWebsiteAdminRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\RunWebsiteAnalyzeJob;
use App\Models\WebsiteAnalyzeRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebsiteAnalyzeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 30)));

        $runs = WebsiteAnalyzeRun::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (WebsiteAnalyzeRun $run): array => $run->toSummaryArray())
            ->values();

        return ApiResponse::success(['runs' => $runs]);
    }

    public function analyze(AnalyzeWebsiteAdminRequest $request): JsonResponse
    {
        $website = trim($request->validated('website'));
        $normalized = Str::startsWith($website, ['http://', 'https://'])
            ? $website
            : 'https://'.$website;

        $run = WebsiteAnalyzeRun::query()->create([
            'user_id' => $request->user()->id,
            'website' => $normalized,
            'status' => WebsiteAnalyzeRun::STATUS_PENDING,
        ]);

        RunWebsiteAnalyzeJob::dispatch($run);

        $run->refresh();

        return ApiResponse::success(
            ['run' => $run->toApiArray()],
            $run->isFinished()
                ? 'Website analyzed successfully.'
                : 'Website analysis queued.',
            $run->isFinished() ? 200 : 202,
        );
    }

    public function show(WebsiteAnalyzeRun $websiteAnalyzeRun): JsonResponse
    {
        if ($websiteAnalyzeRun->user_id !== auth()->id()) {
            abort(403);
        }

        return ApiResponse::success([
            'run' => $websiteAnalyzeRun->toApiArray(),
        ]);
    }
}
