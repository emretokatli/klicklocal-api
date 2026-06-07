# Meta App Review — Instagram publishing (Klicklocal)

Before any **non-tester** customer can connect Instagram or publish, submit **App Review** in the Meta Developer Dashboard.

## Permissions / scopes to request

Klicklocal requests these scopes in OAuth ([backend/config/instagram.php](../backend/config/instagram.php)):

| Scope | Purpose |
|-------|---------|
| `instagram_business_basic` | Profile info after connect |
| `instagram_business_content_publish` | Publish feed images for customers |
| `instagram_business_manage_comments` | Future: comment management |

## What to prepare for review

1. **Privacy Policy URL** — public page describing data use.
2. **Terms of Service** (recommended).
3. **Data deletion instructions** — URL or callback ([Meta data deletion](https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback)).
4. **App domains** — customer app host (`klicklocal.app`) and API host (`api.klicklocal.app`).
5. **Screencast** showing:
   - Customer logs into Klicklocal (your auth).
   - Connect Instagram → OAuth → connected on Social accounts.
   - Create post with image → schedule or publish → content appears on Instagram (or processing state in app).
6. **Test user** credentials for Meta reviewers (Instagram professional account).

## Switch to Live mode

1. Complete App Review for required permissions.
2. Set app to **Live** in Meta dashboard.
3. Deploy production `.env` with Live app **Instagram** App ID/secret.
4. Register production OAuth redirect URI and `INSTAGRAM_MEDIA_PUBLIC_BASE_URL` (HTTPS, publicly reachable storage).

## Staging vs production

- Staging (`api-test.klicklocal.app`) can stay in **Development** with testers only.
- Production (`api.klicklocal.app`) requires **Live** + approved scopes.

See also [META-INSTAGRAM-SETUP.md](./META-INSTAGRAM-SETUP.md).
