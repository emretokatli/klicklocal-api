<?php

namespace App\Providers;

use App\Contracts\Post\PostPublisherInterface;
use App\Services\Ai\AgentSdkWebAnalyzeClient;
use App\Services\Ai\CodeFirstWebAnalyzeClient;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\Contracts\SerpSearchClientInterface;
use App\Services\Ai\Contracts\WebAnalyzeClientInterface;
use App\Services\Ai\FakeOpenAiClient;
use App\Services\Ai\FakeSerpSearchClient;
use App\Services\Ai\FakeWebAnalyzeClient;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\SerpApiSearchClient;
use App\Services\Ai\SocialProfileFetcher;
use App\Services\Ai\WebAnalyzeReportGenerator;
use App\Services\Ai\WebsiteAnalysisService;
use App\Services\Ai\WebsiteDataCollector;
use App\Services\Post\PostPublishingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostPublisherInterface::class, PostPublishingService::class);

        $this->app->bind(OpenAiClientInterface::class, function ($app): OpenAiClientInterface {
            $config = $app['config']->get('services.openai');

            if (($config['driver'] ?? 'fake') === 'fake') {
                return new FakeOpenAiClient;
            }

            return new OpenAiClient(
                apiKey: (string) ($config['key'] ?? ''),
                model: (string) ($config['model'] ?? 'gpt-5'),
                baseUrl: (string) ($config['base_url'] ?? 'https://api.openai.com/v1'),
                timeout: (int) ($config['timeout'] ?? 60),
                sentimentModel: (string) ($config['sentiment_model'] ?? 'gpt-4o-mini'),
            );
        });

        $this->app->singleton(WebsiteAnalysisService::class, function ($app): WebsiteAnalysisService {
            $config = $app['config']->get('services.openai');

            return new WebsiteAnalysisService(
                apiKey: (string) ($config['key'] ?? ''),
                driver: (string) ($config['driver'] ?? 'fake'),
                model: (string) ($config['model'] ?? 'gpt-5'),
                baseUrl: (string) ($config['base_url'] ?? 'https://api.openai.com/v1'),
                timeout: (int) ($config['timeout'] ?? 60),
                urlFetcher: $app->make(\App\Support\SafeUrlFetcher::class),
            );
        });

        $this->app->bind(SerpSearchClientInterface::class, function ($app): SerpSearchClientInterface {
            $config = $app['config']->get('webanalyze');

            if (($config['serp_driver'] ?? 'fake') === 'fake') {
                return new FakeSerpSearchClient;
            }

            return new SerpApiSearchClient(
                apiKey: (string) ($config['serp_api_key'] ?? ''),
            );
        });

        $this->app->singleton(WebAnalyzeReportGenerator::class, function ($app): WebAnalyzeReportGenerator {
            $config = $app['config']->get('webanalyze');

            return new WebAnalyzeReportGenerator(
                apiKey: (string) ($config['api_key'] ?? ''),
                model: (string) ($config['report_model'] ?? 'claude-haiku-4-5-20251001'),
            );
        });

        $this->app->bind(WebAnalyzeClientInterface::class, function ($app): WebAnalyzeClientInterface {
            $config = $app['config']->get('webanalyze');
            $driver = $config['driver'] ?? 'fake';

            if ($driver === 'fake') {
                return new FakeWebAnalyzeClient;
            }

            if ($driver === 'code_first') {
                return new CodeFirstWebAnalyzeClient(
                    dataCollector: $app->make(WebsiteDataCollector::class),
                    searchClient: $app->make(SerpSearchClientInterface::class),
                    socialFetcher: $app->make(SocialProfileFetcher::class),
                    generator: $app->make(WebAnalyzeReportGenerator::class),
                );
            }

            // driver === 'api' — legacy Agent SDK runner
            return new AgentSdkWebAnalyzeClient(
                apiKey: (string) ($config['api_key'] ?? ''),
                nodeBinary: (string) ($config['node_binary'] ?? 'node'),
                scriptPath: (string) ($config['script_path'] ?? base_path('agent-sdk/analyze-website.mjs')),
                projectRoot: (string) ($config['project_root'] ?? dirname(base_path())),
                timeout: (int) ($config['timeout'] ?? 900),
                maxTurns: (int) ($config['max_turns'] ?? 20),
                maxBudgetUsd: (float) ($config['max_budget_usd'] ?? 1.25),
                model: filled($config['model'] ?? null) ? (string) $config['model'] : null,
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
