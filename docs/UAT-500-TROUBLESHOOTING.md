# Deprecated — IONOS webspace 500 troubleshooting

This page diagnosed 500 errors specific to the old IONOS shared-webspace setup
(`.htaccess` rewrite issues, PHP version selection in the hosting panel, missing
`vendor/`). **That deployment strategy has been retired.**

Klicklocal now runs on a dedicated Ubuntu server (nginx + PHP-FPM). For the
current environments and troubleshooting, see:

- **Runbook + troubleshooting table:** [../deploy/README.md](../deploy/README.md)
- **Staging overview:** [UAT.md](UAT.md)

Quick server-side checks:

```bash
# Laravel logs
sudo tail -n 50 /var/www/klicklocal/backend/storage/logs/laravel.log          # production
sudo tail -n 50 /var/www/klicklocal-staging/backend/storage/logs/laravel.log  # staging

# Health endpoints
curl https://api.klicklocal.app/up
curl https://api-test.klicklocal.app/up
```
