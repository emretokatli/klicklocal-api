<?php

namespace App\Services\SocialProviders\DTOs;

readonly class CommentCollectionDTO
{
    /**
     * @param  list<CommentDTO>  $comments
     */
    public function __construct(
        public array $comments = [],
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->comments === [];
    }

    public function count(): int
    {
        return count($this->comments);
    }
}
