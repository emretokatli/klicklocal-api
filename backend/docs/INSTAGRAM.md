# Instagram Business Login & Publishing

Official flow: [Instagram API with Instagram Login — Business Login](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login)

Setup checklist (Meta dashboard, testers, App Review): [docs/META-INSTAGRAM-SETUP.md](../../docs/META-INSTAGRAM-SETUP.md) and [docs/META-APP-REVIEW.md](../../docs/META-APP-REVIEW.md).

## Environment

```env
SOCIAL_INSTAGRAM_DRIVER=api
INSTAGRAM_ENABLED=true
INSTAGRAM_APP_ID=your_instagram_app_id
INSTAGRAM_APP_SECRET=your_instagram_app_secret
# Production example (staging uses api-test.klicklocal.app / test.klicklocal.app)
INSTAGRAM_REDIRECT_URI=https://api.klicklocal.app/api/v1/social-accounts/instagram/callback
INSTAGRAM_FRONTEND_REDIRECT=https://klicklocal.app/social-accounts
FRONTEND_URL=https://klicklocal.app
INSTAGRAM_MEDIA_PUBLIC_BASE_URL=https://api.klicklocal.app/storage
```

Use the **Instagram App ID** from Meta → Instagram → API setup with Instagram login (not the Facebook App ID).

Register the **exact** `INSTAGRAM_REDIRECT_URI` in Meta → Business login settings.

`INSTAGRAM_MEDIA_PUBLIC_BASE_URL` must be a **public HTTPS** URL so Instagram can fetch `image_url` when publishing.

## API endpoints

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/v1/social-accounts/instagram/connect?workspace_id=` | Sanctum + workspace |
| GET | `/api/v1/social-accounts/instagram/callback` | Public (state-validated) |
| POST | `/api/v1/social-accounts/instagram/disconnect?workspace_id=` | Sanctum + workspace |
| GET | `/api/v1/social-accounts/instagram/status?workspace_id=` | Sanctum + workspace |
| POST | `/api/v1/posts/{post}/publish` | Sanctum — publish now to connected accounts |
| POST | `/api/v1/posts/{post}/schedule` | Sanctum — optional `social_account_ids[]` |
| GET | `/api/v1/admin/providers` | Platform admin |
| PUT | `/api/v1/admin/providers/instagram` | Platform admin |

## Connect flow

1. Customer opens `/social-accounts` → **Connect Instagram**
2. Backend creates `oauth_states` row and returns `authorization_url`
3. User approves on Instagram (professional account)
4. Callback exchanges code → long-lived token → stores encrypted token in `social_accounts`
5. Redirect to frontend with `?instagram=connected`

## Publish flow

1. Upload image in **Media** (workspace library)
2. Create **Post** with `content` (caption) and `media_id`
3. **Publish now** (`POST /posts/{id}/publish`) or **Schedule** with optional `social_account_ids`
4. If `social_account_ids` is omitted, all **connected** workspace social accounts are targeted
5. `PublishPostJob` calls `InstagramPublishingService` → Graph API `/{ig-user-id}/media` + `media_publish`

Feed images only (JPEG/PNG/WebP). Video/Reels not implemented yet.

Run queue worker when using `QUEUE_CONNECTION=database`:

```bash
php artisan queue:work
```

## Security

- OAuth `state` is single-use with TTL (default 15 min)
- Tokens use Laravel `encrypted` cast
- Workspace permission: `manage_social_accounts`
- App secret only in server `.env` (`INSTAGRAM_APP_SECRET`)

## Implementation

- OAuth: `InstagramOAuthService`, `InstagramService`
- Publish: `InstagramPublishingService`, `InstagramProvider::publish()`
- Model table: `oauth_states` (`OAuthState` sets `$table = 'oauth_states'`)
