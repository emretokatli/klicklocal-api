<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Services\Media\Exceptions\MediaNotAccessibleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Produces publicly reachable (optionally signed) URLs for media records and
 * verifies that those URLs are actually downloadable. Social networks
 * (Instagram, TikTok PULL_FROM_URL, Facebook) fetch the asset themselves, so
 * the URL must resolve to the file over the public internet.
 */
class MediaUrlService
{
    /**
     * Absolute, publicly reachable URL for a media record. Returns a signed
     * temporary URL when the disk supports it and signing is enabled.
     */
    public function publicUrl(Media $media): string
    {
        $disk = (string) config('media.disk', 'public');
        $ttl = (int) config('media.signed_url_ttl_minutes', 0);

        if ($ttl > 0) {
            try {
                return Storage::disk($disk)->temporaryUrl(
                    $media->file_path,
                    now()->addMinutes($ttl),
                );
            } catch (Throwable) {
                // Disk does not support temporary URLs (e.g. local) — fall back
                // to the plain public URL below.
            }
        }

        return $this->publicBaseUrl().'/'.ltrim($media->file_path, '/');
    }

    public function isPubliclyAccessible(Media $media): bool
    {
        try {
            $this->ensurePubliclyAccessible($media);

            return true;
        } catch (MediaNotAccessibleException) {
            return false;
        }
    }

    /**
     * @throws MediaNotAccessibleException when the media URL is not reachable
     *                                     or does not serve an image/video.
     */
    public function ensurePubliclyAccessible(Media $media): void
    {
        $url = $this->publicUrl($media);
        $timeout = (int) config('media.verify_timeout_seconds', 15);

        try {
            $response = Http::timeout($timeout)->head($url);

            // Some servers reject HEAD — retry with a single-byte ranged GET.
            if (in_array($response->status(), [403, 405], true)) {
                $response = Http::timeout($timeout)
                    ->withHeaders(['Range' => 'bytes=0-0'])
                    ->get($url);
            }
        } catch (Throwable $e) {
            throw MediaNotAccessibleException::unreachable($media, $url, $e->getMessage());
        }

        $status = $response->status();

        // 200 OK or 206 Partial Content (ranged GET) are both acceptable.
        if (! $response->successful() && $status !== 206) {
            throw MediaNotAccessibleException::badStatus($media, $url, $status);
        }

        $contentType = strtolower(trim((string) $response->header('Content-Type')));

        if ($contentType !== '' && ! Str::startsWith($contentType, ['image/', 'video/'])) {
            throw MediaNotAccessibleException::notMedia($media, $url, $contentType);
        }
    }

    private function publicBaseUrl(): string
    {
        $base = config('media.public_base_url');

        if (! filled($base)) {
            $base = rtrim((string) config('app.url'), '/').'/storage';
        }

        return rtrim((string) $base, '/');
    }
}
