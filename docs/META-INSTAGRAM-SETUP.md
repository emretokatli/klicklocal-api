# Meta / Instagram setup for Klicklocal (solution provider)

One Meta app powers all customers. Each customer connects their own Instagram **professional** account via OAuth.

**Do not** use the Facebook JavaScript SDK (`FB.init`, Login Button) for this flow. Klicklocal uses [Instagram Business Login](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login) (server redirect).

## 1. Create the Meta app

1. Go to [developers.facebook.com](https://developers.facebook.com/) → **My Apps** → **Create App**.
2. Add product: **Instagram** → **Instagram API with Instagram login** (Business Login).
3. Do **not** use only “Facebook Login for websites” for Instagram connect.

## 2. Credentials (critical)

| Field | Where in Meta | Klicklocal |
|-------|----------------|------------|
| **Instagram App ID** | Instagram → **API setup with Instagram login** | `INSTAGRAM_APP_ID` + Admin → Providers |
| **Instagram App Secret** | Same section | `INSTAGRAM_APP_SECRET` in server `.env` only |
| Facebook App ID (top of dashboard) | App Settings → Basic | **Do not use** for OAuth — causes `Invalid platform app` |

## 3. OAuth redirect URIs

Register the **exact** callback URL per environment in Meta → Instagram → Business login → **OAuth redirect URIs**:

| Environment | URL |
|-------------|-----|
| Local XAMPP | `http://localhost:1981/klicklocal/backend/public/api/v1/social-accounts/instagram/callback` |
| UAT | `https://gastrocycle.com/public/api/v1/social-accounts/instagram/callback` |
| Production | `https://<your-api-host>/public/api/v1/social-accounts/instagram/callback` |

Must match `INSTAGRAM_REDIRECT_URI` in `.env` character-for-character.

**Local tip:** Meta may block `http://localhost`. Test connect on **HTTPS UAT** first, or use ngrok and register that HTTPS callback.   

## 4. Development testers

1. Meta app → **Roles** → **Instagram Testers** → add Instagram accounts.
2. Each tester accepts: Instagram app → **Settings** → **Website permissions** → **Tester invites**.
3. Only **professional** (Business/Creator) accounts can complete Business Login.

## 5. Backend `.env` (per environment)

```env
SOCIAL_INSTAGRAM_DRIVER=api
INSTAGRAM_ENABLED=true
INSTAGRAM_APP_ID=
INSTAGRAM_APP_SECRET=
INSTAGRAM_REDIRECT_URI="${APP_URL}/api/v1/social-accounts/instagram/callback"
INSTAGRAM_FRONTEND_REDIRECT="${FRONTEND_URL}/social-accounts"
FRONTEND_URL=http://localhost:3000
# Public base URL for post images (Instagram must fetch this). Use HTTPS in production.
INSTAGRAM_MEDIA_PUBLIC_BASE_URL="${APP_URL}/storage"
```

Then:

```bash
php artisan migrate
php artisan config:clear
```

**Admin → Providers:** enable Instagram, paste **Instagram App ID**, verify callback URL.

## 6. Customer flow in Klicklocal

1. Log in → select workspace.
2. **Social accounts** → **Connect Instagram**.
3. Approve on Instagram → return with `?instagram=connected`.
4. Create post with **image** (media library) → **Schedule** or **Publish now** with Instagram selected.

## 7. Queue worker (scheduled / publish)

Publishing runs via `PublishPostJob`. With `QUEUE_CONNECTION=database`:

```bash
php artisan queue:work
```

For local quick tests, use `QUEUE_CONNECTION=sync` in `.env`.

## Related docs

- [backend/docs/INSTAGRAM.md](../backend/docs/INSTAGRAM.md) — API endpoints & publishing
- [META-APP-REVIEW.md](./META-APP-REVIEW.md) — App Review before public launch
