# Deprecated — webspace (IONOS) deployment

This guide covered the old shared-webspace deployment (`gastrocycle.com` with a
`/public` path and a remote IONOS MySQL host). **That strategy has been retired.**

Klicklocal now runs on a dedicated Ubuntu server with isolated production and
staging environments on `klicklocal.app` subdomains:

| Environment | Branch | API |
|-------------|--------|-----|
| Production | `main` | `https://api.klicklocal.app` |
| Staging | `develop` | `https://api-test.klicklocal.app` |

Follow the current runbook instead:

- **Server setup & deploys:** [../deploy/README.md](../deploy/README.md)
- **Staging overview:** [UAT.md](UAT.md)
- **Frontend on Vercel:** [VERCEL-DEPLOY.md](VERCEL-DEPLOY.md)
