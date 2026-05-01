English / [日本語](./deployment.ja.md)

# Deployment

> **This document is optional reference material.** This repository is distributed as a reference implementation of Fincode integration. The intended usage flow is: clone → run locally to evaluate → adopt into your own service. Use this document only if, after evaluation, you choose to host this code as-is in your own environment.

Production deployment guide. The template targets a typical PHP-on-Linux setup (Nginx + PHP-FPM + MySQL + Supervisor). Adapt as needed for managed platforms.

## Pre-flight checklist

| Item | Required | Notes |
| --- | --- | --- |
| `APP_ENV=production` | ✅ | Disables debug pages and noisy logging. |
| `APP_DEBUG=false` | ✅ | Stack traces must not leak. |
| `APP_KEY` | ✅ | Run `php artisan key:generate` once; store securely. |
| `APP_URL` | ✅ | Must match the actual hostname for absolute URLs and CSRF. |
| `FINCODE_API_KEY=m_prod_...` | ✅ | Production secret key. **Not** `m_test_*`. |
| `FINCODE_PUBLIC_KEY=p_prod_...` | ✅ | Production public key for browser tokenization. |
| `FINCODE_BASE_URL=https://api.fincode.jp` | ✅ | Production API endpoint. |
| `SESSION_DRIVER=database` (or `redis`) | ✅ | Not `array` / `file` for multi-instance setups. |
| `CACHE_STORE=database` (or `redis`) | ✅ | The Circuit Breaker uses the cache. Single-instance memory cache breaks across replicas. |
| `QUEUE_CONNECTION=database` (or `redis`) | ✅ | A worker must be running — see "Queue worker" below. |
| `MAIL_MAILER` | ✅ | Set to a real driver (`smtp`, `ses`, `postmark`, …). Not `log`. |
| `LOG_CHANNEL=stack` | recommended | The default `stack` driver writes to `storage/logs/laravel.log` and stderr. |
| `TRUSTED_PROXIES` | if behind a load balancer | Otherwise CSRF and rate limiting may key on the LB IP. |
| HTTPS | ✅ | Cookies are flagged Secure; mixed content breaks Inertia. |

> **Validate at boot.** `app/Services/Fincode/FincodeApiConfigValidator.php` and `FincodeConfigValidator.php` raise on missing / inconsistent Fincode config. Don't bypass them.

## Build

```bash
# 1. Pull source
git fetch --all
git checkout <release-tag-or-sha>

# 2. PHP deps (no dev)
composer install --no-dev --optimize-autoloader --no-interaction

# 3. JS deps and build
npm ci
npm run build

# 4. Apply migrations
php artisan migrate --force

# 5. Cache framework artifacts
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

After this, `composer dev` is **not** used in production. Each Laravel cache step trades flexibility for boot speed; clear them when deploying a new build.

## Web server

A minimal Nginx config:

```nginx
server {
    listen 443 ssl http2;
    server_name your.domain.example;

    root /var/www/subscription-app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # built assets — long cache
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Redirect HTTP → HTTPS at the load balancer or with a separate `listen 80` block.

## Queue worker (mandatory)

Events (`SubscriptionCreated`, `CardRegistered`, audit log writes via listeners, mail) are processed by the queue. **Run `queue:work` as a long-lived process under Supervisor** — `queue:listen` from `composer dev` is for local development only.

`/etc/supervisor/conf.d/subscription-app-worker.conf`:

```ini
[program:subscription-app-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/subscription-app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/subscription-app-worker.log
stopwaitsecs=3600
```

Reload after deploys so workers pick up new code:

```bash
php artisan queue:restart
```

`--max-time=3600` makes each worker exit after an hour so Supervisor restarts it — this avoids long-term memory creep without forcing a deploy.

## Scheduler (forward-looking)

This template does not yet schedule any task, but if a fork adds one (e.g. a daily reconciler against Fincode), wire up the Laravel scheduler:

```cron
* * * * * cd /var/www/subscription-app && php artisan schedule:run >> /dev/null 2>&1
```

## Logs and rotation

`storage/logs/laravel.log` grows unbounded by default. Configure logrotate:

```
/var/www/subscription-app/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    copytruncate
}
```

For multi-host setups, ship logs to a central system (CloudWatch, Datadog, Loki, etc.) via the `LOG_CHANNEL=stack` driver or a syslog channel.

## Security headers

`app/Http/Middleware/SecurityHeaders.php` adds CSP, HSTS, X-Frame-Options, etc. The CSP **report-only** mode is convenient when first deploying — collect violations at `POST /api/security/csp-reports` for a few days, tune the policy, then enforce.

`config/security.php` controls which headers are emitted; review it once before going live.

## Rotating Fincode API keys

When you rotate keys in the Fincode console:

1. Issue a new pair (`m_prod_*`, `p_prod_*`).
2. Stage them in your secrets store.
3. Deploy the new env vars **without removing the old key in Fincode yet**.
4. Confirm new requests succeed (check the structured Fincode logs).
5. Revoke the old key in the Fincode console.

The Circuit Breaker state is held in the cache — a key rotation does **not** require flushing it.

## Rollback

Migrations should always have a working `down()` (this is the team convention; check before merging schema changes). To roll back one deploy:

```bash
git checkout <previous-tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate:rollback --step=N   # only if the bad release added migrations
php artisan config:cache route:cache view:cache event:cache
php artisan queue:restart
```

If migrations cannot be rolled back safely (e.g. a destructive change), the safer path is **forward-fix** — deploy a new release that restores the necessary state.

## Backups

This template does not provision backups; that's an infrastructure concern. At a minimum, take logical backups of MySQL daily and verify restoration quarterly. The `audit_logs` table is irreplaceable evidence — back it up.

## Observability

Out of the box you have:

- Structured app logs (`storage/logs/laravel.log`).
- Audit log table (`audit_logs`).
- Circuit Breaker state in cache (probe via tinker: `app(CircuitBreaker::class)->getState()`).

There is no built-in metrics endpoint or APM hook. If you need them, integrate Laravel Pulse, OpenTelemetry, or a vendor SDK in a fork.

## Where to read next

- [../getting-started/local-development.md](../getting-started/local-development.md) — what's different from local.
- [../architecture/error-handling.md](../architecture/error-handling.md) — what your monitoring should alert on.
- [../customization/index.md](../customization/index.md) — what to change before going to production.
