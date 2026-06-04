<?php

use App\Http\Controllers\Api\V1\Admin\AiPromptController;
use App\Http\Controllers\Api\V1\Admin\CouponController;
use App\Http\Controllers\Api\V1\Admin\PlanController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\SocialProviderController;
use App\Http\Controllers\Api\V1\InstagramSocialAccountController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\TransactionController;
use App\Http\Controllers\Api\V1\Admin\UsageController as AdminUsageController;
use App\Http\Controllers\Api\V1\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\UsageController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('social-accounts/instagram/callback', [InstagramSocialAccountController::class, 'callback']);

    Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->middleware('stripe.webhook');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    Route::middleware(['auth:sanctum', 'customer'])->group(function (): void {
        Route::apiResource('workspaces', WorkspaceController::class);

        Route::middleware('workspace.team')->group(function (): void {
            Route::get('posts', [PostController::class, 'index']);
            Route::post('posts', [PostController::class, 'store']);
            Route::get('posts/{post}', [PostController::class, 'show']);
            Route::put('posts/{post}', [PostController::class, 'update']);
            Route::delete('posts/{post}', [PostController::class, 'destroy']);
            Route::post('posts/{post}/schedule', [PostController::class, 'schedule'])
                ->middleware('feature.quota:scheduled_posts_monthly');
            Route::post('posts/{post}/publish', [PostController::class, 'publish']);

            Route::get('media', [MediaController::class, 'index']);
            Route::post('media/upload', [MediaController::class, 'upload'])
                ->middleware('feature.quota:media_uploads_monthly');

            Route::get('billing', [BillingController::class, 'index']);
            Route::get('subscription', [SubscriptionController::class, 'show']);
            Route::post('subscription', [SubscriptionController::class, 'subscribe']);
            Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);
            Route::get('usage', [UsageController::class, 'index']);
            Route::get('invoices', [InvoiceController::class, 'index']);

            Route::prefix('social-accounts/instagram')->group(function (): void {
                Route::get('connect', [InstagramSocialAccountController::class, 'connect']);
                Route::post('disconnect', [InstagramSocialAccountController::class, 'disconnect']);
                Route::get('status', [InstagramSocialAccountController::class, 'status']);
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

            Route::get('providers', [SocialProviderController::class, 'index']);
            Route::put('providers/{provider}', [SocialProviderController::class, 'update']);
            Route::put('providers/instagram', [SocialProviderController::class, 'updateInstagram']);
        });
});
