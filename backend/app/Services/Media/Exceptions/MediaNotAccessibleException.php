<?php

namespace App\Services\Media\Exceptions;

use App\Models\Media;
use RuntimeException;

class MediaNotAccessibleException extends RuntimeException
{
    public static function unreachable(Media $media, string $url, string $reason): self
    {
        return new self(
            "Media #{$media->id} ({$media->file_name}) is not publicly reachable at {$url}: {$reason}. "
            .'Social networks must be able to download the file. Check storage visibility and APP_URL/MEDIA_PUBLIC_BASE_URL.',
        );
    }

    public static function badStatus(Media $media, string $url, int $status): self
    {
        return new self(
            "Media #{$media->id} ({$media->file_name}) is not publicly accessible: {$url} returned HTTP {$status}. "
            .'Ensure the file is stored with public visibility and the URL is reachable from the internet.',
        );
    }

    public static function notMedia(Media $media, string $url, string $contentType): self
    {
        return new self(
            "Media #{$media->id} ({$media->file_name}) at {$url} returned an unexpected content type "
            ."[{$contentType}]; expected an image or video.",
        );
    }
}
