<?php

use App\Http\Controllers\Api\V1\Admin\AiPromptController;
use App\Http\Controllers\Api\V1\Admin\CouponController;
use App\Http\Controllers\Api\V1\Admin\PlanController;
use App\Http\Controllers\Api\V1\Admin\QuotaAddonController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\SocialProviderController;
use App\Http\Controllers\Api\V1\InstagramSocialAccountController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\TransactionController;
use App\Http\Controllers\Api\V1\Admin\UsageController as AdminUsageController;
use App\Http\Controllers\Api\V1\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AiContentController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\QuotaTopupController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BusinessProfileController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\RevenueCatWebhookController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TikTokSocialAccountController;
use App\Http\Controllers\Api\V1\UsageController;
use App\Http\Controllers\Api\V1\WebsiteAnalysisController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('social-accounts/instagram/callback', [InstagramSocialAccountController::class, 'callback']);
    Route::get('social-accounts/tiktok/callback', [TikTokSocialAccountController::class, 'callback']);

    Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->middleware('stripe.webhook');

    Route::post('webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle'])
        ->middleware('revenuecat.webhook');

    Route::post('onboarding/analyze-website', [WebsiteAnalysisController::class, 'analyze'])
        ->middleware('throttle:6,1');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('register-email', [AuthController::class, 'registerEmail']);
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::get('onboarding', [AuthController::class, 'onboardingStatus']);
            Route::patch('onboarding', [AuthController::class, 'updateOnboarding']);
            Route::post('onboarding/complete', [AuthController::class, 'completeOnboarding']);
        });
    });

    Route::middleware(['auth:sanctum', 'customer'])->group(function (): void {
        Route::apiResource('workspaces', WorkspaceController::class);

        Route::middleware('workspace.team')->group(function (): void {
            Route::get('workspaces/{workspace}/business-profile', [BusinessProfileController::class, 'show']);
            Route::put('workspaces/{workspace}/business-profile', [BusinessProfileController::class, 'update']);
            Route::patch('workspaces/{workspace}/onboarding', [OnboardingController::class, 'update']);

            Route::post('ai/generate', [AiContentController::class, 'generate'])
                ->middleware(['subscription.required', 'feature.quota:ai_generation']);
            Route::post('ai/generate-image', [AiContentController::class, 'generateImage'])
                ->middleware(['subscription.required', 'feature.quota:ai_generation']);
            Route::get('ai/generations', [AiContentController::class, 'index']);

            Route::get('posts', [PostController::class, 'index']);
            Route::get('posts/{post}', [PostController::class, 'show']);
            Route::middleware('subscription.required')->group(function (): void {
                Route::post('posts/quick-publish', [PostController::class, 'quickPublish'])
                    ->middleware('feature.quota:scheduled_posts_monthly');
                Route::post('posts', [PostController::class, 'store']);
                Route::put('posts/{post}', [PostController::class, 'update']);
                Route::delete('posts/{post}', [PostController::class, 'destroy']);
                Route::post('posts/{post}/schedule', [PostController::class, 'schedule'])
                    ->middleware('feature.quota:scheduled_posts_monthly');
                Route::post('posts/{post}/publish', [PostController::class, 'publish']);
            });

            Route::get('media', [MediaController::class, 'index']);
            Route::post('media/upload', [MediaController::class, 'upload'])
                ->middleware(['subscription.required', 'feature.quota:media_uploads_monthly']);

            Route::get('billing', [BillingController::class, 'index']);
            Route::get('transactions', [BillingController::class, 'transactions']);
            Route::get('subscription', [SubscriptionController::class, 'show']);
            Route::post('subscription', [SubscriptionController::class, 'subscribe']);
            Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);
            Route::get('usage', [UsageController::class, 'index']);
            Route::get('invoices', [InvoiceController::class, 'index']);

            Route::get('quota/packages', [QuotaTopupController::class, 'listPackages']);
            Route::post('quota/topup', [QuotaTopupController::class, 'purchase'])
                ->middleware('subscription.required');

            // Comments (workspace scoped, no subscription required)
            Route::get('/comments', [CommentController::class, 'index']);
            Route::post('/comments', [CommentController::class, 'store']);

            // Analytics (workspace scoped, no subscription required)
            Route::get('/analytics/kpi', [AnalyticsController::class, 'kpi']);

            Route::prefix('social-accounts/instagram')->group(function (): void {
                Route::get('connect', [InstagramSocialAccountController::class, 'connect']);
                Route::post('disconnect', [InstagramSocialAccountController::class, 'disconnect']);
                Route::get('status', [InstagramSocialAccountController::class, 'status']);
            });

            Route::prefix('social-accounts/tiktok')->group(function (): void {
                Route::get('connect', [TikTokSocialAccountController::class, 'connect']);
                Route::post('disconnect', [TikTokSocialAccountController::class, 'disconnect']);
                Route::get('status', [TikTokSocialAccountController::class, 'status']);
            });
        });
    });

    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'platform.admin'])
        ->group(function (): void {
            Route::get('users', [UserController::class, 'index']);
            Route::get('users/{user}', [UserController::class, 'show']);
            Route::put('users/{user}/roles', [UserController::class, 'updateRoles']);

            Route::get('plans/feature-keys', [PlanController::class, 'featureKeys']);
            Route::apiResource('plans', PlanController::class);

            Route::get('workspaces', [AdminWorkspaceController::class, 'index']);

            Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
            Route::post('subscriptions/demo', [AdminSubscriptionController::class, 'grantDemo']);
            Route::post('subscriptions', [AdminSubscriptionController::class, 'store']);
            Route::delete('subscriptions/{subscription}', [AdminSubscriptionController::class, 'destroy']);

            Route::get('transactions', [TransactionController::class, 'index']);

            Route::get('coupons', [CouponController::class, 'index']);
            Route::post('coupons', [CouponController::class, 'store']);
            Route::put('coupons/{coupon}', [CouponController::class, 'update']);

            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);

            Route::get('ai-prompts', [AiPromptController::class, 'index']);
            Route::post('ai-prompts', [AiPromptController::class, 'store']);
            Route::get('ai-prompts/{aiPromptTemplate}', [AiPromptController::class, 'show']);
            Route::put('ai-prompts/{aiPromptTemplate}', [AiPromptController::class, 'update']);
            Route::patch('ai-prompts/{aiPromptTemplate}/active', [AiPromptController::class, 'setActive']);

            Route::get('usage', [AdminUsageController::class, 'index']);

            Route::get('quota-addons', [QuotaAddonController::class, 'index']);
            Route::post('quota-addons', [QuotaAddonController::class, 'store']);

            Route::get('providers', [SocialProviderController::class, 'index']);
            Route::put('providers/{provider}', [SocialProviderController::class, 'update']);
            Route::put('providers/instagram', [SocialProviderController::class, 'updateInstagram']);
        });
});
