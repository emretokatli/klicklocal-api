<?php

return [

    /*
    | Disk that stores uploaded media. The public disk serves files over HTTP;
    | an S3-compatible disk additionally supports signed (temporary) URLs.
    */
    'disk' => env('MEDIA_DISK', 'public'),

    /*
    | Base URL used to build public media URLs. When null we fall back to
    | APP_URL/storage. Point this at a CDN if media is fronted by one.
    */
    'public_base_url' => env('MEDIA_PUBLIC_BASE_URL'),

    /*
    | When > 0 and the disk supports it, media is served via signed temporary
    | URLs valid for this many minutes (used for IG / TikTok PULL_FROM_URL when
    | files live on private object storage). 0 = plain public URLs.
    */
    'signed_url_ttl_minutes' => (int) env('MEDIA_SIGNED_URL_TTL_MINUTES', 0),

    /*
    | Verify that media is publicly reachable before social publishing. Social
    | networks download the asset themselves, so an unreachable URL fails the
    | post. Disable only in environments where the check cannot reach itself.
    */
    'verify_public_access' => (bool) env('MEDIA_VERIFY_PUBLIC_ACCESS', true),

    'verify_timeout_seconds' => (int) env('MEDIA_VERIFY_TIMEOUT_SECONDS', 15),

];
