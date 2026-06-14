<?php

namespace App\Services\Trends\Contracts;

use App\Models\TrendAudio;
use App\Models\TrendFormat;
use App\Models\TrendHashtag;
use App\Models\TrendTopic;
use Illuminate\Support\Collection;

interface TrendProviderInterface
{
    /**
     * The driver key backing this provider (e.g. 'fake', 'api').
     */
    public function driver(): string;

    /**
     * Trending content topics, optionally filtered by business category.
     *
     * @return Collection<int, TrendTopic>
     */
    public function topics(?string $category = null, int $limit = 20): Collection;

    /**
     * Trending hashtags, optionally filtered by business category.
     *
     * @return Collection<int, TrendHashtag>
     */
    public function hashtags(?string $category = null, int $limit = 20): Collection;

    /**
     * Trending audio tracks, optionally filtered by platform.
     *
     * @return Collection<int, TrendAudio>
     */
    public function audio(?string $platform = null, int $limit = 20): Collection;

    /**
     * Trending content formats, optionally filtered by platform.
     *
     * @return Collection<int, TrendFormat>
     */
    public function formats(?string $platform = null, int $limit = 20): Collection;

    public function supports(string $capability): bool;

    /**
     * @return list<string>
     */
    public static function capabilities(): array;
}
