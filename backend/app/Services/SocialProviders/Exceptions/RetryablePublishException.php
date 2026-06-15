<?php

namespace App\Services\SocialProviders\Exceptions;

use RuntimeException;

/**
 * Thrown when one or more platforms had a transient (retryable) failure and the
 * publish job should be retried so only those platforms are re-attempted.
 */
class RetryablePublishException extends RuntimeException
{
    public static function platformsPending(int $count): self
    {
        return new self("{$count} platform(s) had a transient failure and will be retried.");
    }
}
