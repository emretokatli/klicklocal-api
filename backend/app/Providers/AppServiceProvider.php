<?php

namespace App\Providers;

use App\Contracts\Post\PostPublisherInterface;
use App\Services\Post\PostPublishingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostPublisherInterface::class, PostPublishingService::class);
    }

    public function boot(): void
    {
        //
    }
}
