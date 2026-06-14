<?php

namespace Tests\Unit;

use App\Models\TrendTopic;
use App\Services\Trends\Contracts\TrendProviderInterface;
use App\Services\Trends\Exceptions\TrendProviderException;
use App\Services\Trends\Factory\TrendProviderFactory;
use App\Services\Trends\Fake\FakeTrendProvider;
use Database\Seeders\TrendSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FakeTrendProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_resolves_fake_provider_by_default(): void
    {
        config(['trends.driver' => 'fake']);

        $provider = app(TrendProviderFactory::class)->make();

        $this->assertInstanceOf(FakeTrendProvider::class, $provider);
        $this->assertInstanceOf(TrendProviderInterface::class, $provider);
        $this->assertSame('fake', $provider->driver());
    }

    public function test_factory_throws_for_unknown_driver(): void
    {
        $this->expectException(TrendProviderException::class);

        app(TrendProviderFactory::class)->make('does-not-exist');
    }

    public function test_provider_returns_seeded_trends(): void
    {
        $this->seed(TrendSeeder::class);

        $provider = new FakeTrendProvider;

        $topics = $provider->topics();
        $this->assertInstanceOf(Collection::class, $topics);
        $this->assertGreaterThan(0, $topics->count());
        $this->assertInstanceOf(TrendTopic::class, $topics->first());

        $this->assertGreaterThan(0, $provider->hashtags()->count());
        $this->assertGreaterThan(0, $provider->audio()->count());
        $this->assertGreaterThan(0, $provider->formats()->count());
    }

    public function test_topics_are_ordered_by_score_descending(): void
    {
        $this->seed(TrendSeeder::class);

        $scores = (new FakeTrendProvider)->topics()->pluck('score')->all();

        $sorted = $scores;
        rsort($sorted);

        $this->assertSame($sorted, $scores);
    }

    public function test_category_filter_is_applied(): void
    {
        $this->seed(TrendSeeder::class);

        $topics = (new FakeTrendProvider)->topics('beauty');

        $this->assertGreaterThan(0, $topics->count());
        $this->assertTrue($topics->every(fn (TrendTopic $t) => $t->category === 'beauty'));
    }

    public function test_limit_is_respected(): void
    {
        $this->seed(TrendSeeder::class);

        $this->assertLessThanOrEqual(2, (new FakeTrendProvider)->topics(null, 2)->count());
    }

    public function test_falls_back_to_placeholder_when_no_seeded_data(): void
    {
        // No seeding: tables are empty, provider should still return placeholders.
        $provider = new FakeTrendProvider;

        $this->assertGreaterThan(0, $provider->topics()->count());
        $this->assertGreaterThan(0, $provider->hashtags()->count());
        $this->assertGreaterThan(0, $provider->audio()->count());
        $this->assertGreaterThan(0, $provider->formats()->count());
        $this->assertSame(0, TrendTopic::query()->count());
    }

    public function test_platform_filter_applies_to_audio(): void
    {
        $this->seed(TrendSeeder::class);

        $audio = (new FakeTrendProvider)->audio('tiktok');

        $this->assertGreaterThan(0, $audio->count());
        $this->assertTrue($audio->every(fn ($a) => $a->platform === 'tiktok'));
    }

    public function test_capabilities_are_reported(): void
    {
        $provider = new FakeTrendProvider;

        $this->assertTrue($provider->supports('topics'));
        $this->assertTrue($provider->supports('hashtags'));
        $this->assertFalse($provider->supports('nonsense'));
        $this->assertContains('audio', FakeTrendProvider::capabilities());
    }
}
