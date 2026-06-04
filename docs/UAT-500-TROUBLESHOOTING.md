# UAT: 500 error even on ping.php

If **`ping.php`** and **`ping.html`** both return 500, Laravel and `.env` are **not** the cause yet.

## Step A — Static HTML (no PHP)

Upload **`public/ping.html`** and open:

```text
https://gastrocycle.com/klicklocal/public/ping.html
```

| Result | Meaning |
|--------|---------|
| Shows **OK** | Web server works; **PHP is broken or disabled** for this folder |
| **500** | Problem above `public/` (parent `.htaccess`, path, permissions) |

## Step B — Remove ALL rewrite files (WinSCP)

Delete or rename on the server:

| File | Action |
|------|--------|
| `/klicklocal/.htaccess` | Delete or empty |
| `/klicklocal/public/.htaccess` | Rename to `.htaccess.bak` |
| Web root `/htdocs/.htaccess` | Check content — broken rules affect everything |

Test again: `ping.html` then `ping.php`.

## Step C — IONOS / webspace panel

1. **Domain** → **PHP settings** → enable **PHP 8.2** or **8.3** for the site (not 7.4).
2. Apply PHP to the folder that contains `klicklocal` (or whole domain).
3. **Error log** (domain → logs): open the latest line for your test time — paste that message when asking for help.

## Step D — File permissions (FTP / WinSCP)

| Path | Permission |
|------|------------|
| `klicklocal/public/ping.php` | **644** |
| `klicklocal/public/` | **755** |
| Folders up to `klicklocal/` | **755** |

## Step E — Isolated test outside Laravel

Create on the server (via file manager), **outside** `klicklocal`:

```text
/htdocs/test-ok.html   → content: OK
/htdocs/test-ok.php    → content: <?php echo 'OK';
```

Open:

```text
https://gastrocycle.com/test-ok.html
https://gastrocycle.com/test-ok.php
```

| Result | Meaning |
|--------|---------|
| HTML OK, PHP 500 | PHP not enabled / wrong version globally |
| Both OK | Problem is only inside `/klicklocal/` (path or local `.htaccess`) |
| Both 500 | Domain / account / root `.htaccess` issue — contact host support |

## Step F — When ping works

1. Upload the new `public/.htaccess` from the repo (no `RewriteBase`).
2. Test `ping.php` → `uat-check.php` → `up`.
3. Ensure **`vendor/`** is uploaded and **`storage/`** is writable (775).

## Common IONOS causes

- PHP 7.x selected (Laravel 12 needs **8.2+**).
- `mod_rewrite` rules in **parent** folder.
- Upload incomplete: missing `vendor/`.
- `.env` wrong — causes 500 only **after** Laravel boots (not on bare `ping.php`).
