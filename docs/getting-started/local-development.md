English / [日本語](./local-development.ja.md)

# Local development

End-to-end setup for running the app locally. The README covers the short version; this document covers everything else.

## Prerequisites

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.3+ | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | 22+ | `node -v`. **[Volta](https://volta.sh/) recommended** — this repo's `package.json` pins Node 22.11.0 via the `volta` field, so Volta auto-switches on `cd`. [nvm](https://github.com/nvm-sh/nvm) is a fine alternative. |
| MySQL | 8.0+ or MariaDB 10.6+ | Tests target MariaDB; either works for development. |
| Fincode account | Test mode | See [fincode-setup.md](./fincode-setup.md). |

Required PHP extensions are the Laravel 12 defaults: `mbstring`, `pdo_mysql`, `bcmath`, `intl`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `dom`, `curl`.

## 0. Recommended toolchain

We recommend **Volta + Mailpit + an active pre-commit hook** on every OS. On Windows, also use **Git Bash** (bundled with Git for Windows) as your shell — it avoids subtle issues with `npx concurrently` under PowerShell.

| Tool | Purpose | Windows | macOS | Linux |
| --- | --- | --- | --- | --- |
| Git for Windows / Git Bash | Shell for `composer dev` | <https://git-scm.com/download/win> | bundled | bundled |
| [Volta](https://volta.sh/) | Auto-pin Node.js 22 | `winget install Volta.Volta` | `curl https://get.volta.sh \| bash` | `curl https://get.volta.sh \| bash` |
| [Mailpit](https://mailpit.axllent.org/) | Mail UI in development | `winget install axllent.mailpit` or `scoop install mailpit` | `brew install mailpit` | official installer (see below) |

After installing Volta, run the following once. Then `cd` into this repo will automatically select Node 22.11.0 (the version pinned in `package.json`'s `volta` field):

```bash
volta install node@22.11.0
```

Everything above is OSS / free.

## 1. Create the database

```sql
CREATE DATABASE subscription_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL ON subscription_app.* TO 'app'@'localhost';
```

For tests, also create:

```sql
CREATE DATABASE subscription_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON subscription_app_test.* TO 'app'@'localhost';
```

## 2. Run `composer setup`

```bash
composer setup
```

This script (defined in `composer.json`) does four things:

1. `composer install` — install PHP deps.
2. Copy `.env.example` to `.env` if `.env` doesn't exist.
3. `php artisan key:generate` — generate `APP_KEY`.
4. `npm install && npm run build` — install JS deps and produce a production build (so the app works even if Vite dev server is not running).

> Migrations are **not** run here. They are split into `composer setup:db` (step 4 below) because they require valid DB credentials, which you set in the next step.

If a step fails, fix the cause and run the **individual** command — re-running `composer setup` is safe but slower.

## 3. Set environment variables

Edit `.env`:

```ini
APP_NAME="Subscription App"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_app
DB_USERNAME=app
DB_PASSWORD=change-me

FINCODE_API_KEY=m_test_xxxxxxxxxxxxxxxxxxxxxxx
FINCODE_PUBLIC_KEY=p_test_xxxxxxxxxxxxxxxxxxxxxxx
FINCODE_BASE_URL=https://api.test.fincode.jp

MAIL_MAILER=log    # writes mail to storage/logs/laravel.log; switch to mailpit if you have it
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Get the Fincode keys from [fincode-setup.md](./fincode-setup.md).

## 4. Run migrations

```bash
composer setup:db
```

This runs `php artisan migrate --force`. Re-run it any time you need to apply new migrations after `git pull`.

## 5. Run the app

```bash
composer dev
```

This starts four processes side-by-side via `npx concurrently`:

| Process | What | Default port |
| --- | --- | --- |
| `php artisan serve` | Laravel HTTP server | `:8000` |
| `php artisan queue:listen --tries=1` | Queue worker (events, mail) | — |
| `php artisan pail --timeout=0` | Tail of `storage/logs/laravel.log` | — |
| `npm run dev` | Vite dev server (HMR) | `:5173` |

Open <http://localhost:8000>. Inertia loads the React app; Vite handles hot reload of frontend code.

`Ctrl+C` stops all four (`--kill-others` is set).

## 6. Seed sample data (optional)

```bash
php artisan migrate:fresh --seed
```

This wipes the DB and re-runs seeders. Use it when iterating on schema or seeders. **Never run it against a database that has real user data.**

## 7. Mail in development

The default `.env.example` sets `MAIL_MAILER=log`. Outgoing mail (registration, email verification) is appended to `storage/logs/laravel.log` and visible in the `pail` pane of `composer dev`.

For a proper UI, install **Mailpit** (recommended):

```bash
# macOS
brew install mailpit

# Windows
winget install axllent.mailpit
# or: scoop install mailpit

# Linux (official installer)
sudo bash < <(curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh)
```

Run `mailpit` in a separate terminal — it listens on `:1025` (SMTP) and `:8025` (UI). Then point `.env` at it:

```ini
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

Open <http://localhost:8025> to inspect outgoing mail (registration, email verification) in real time.

## 8. Manual checks via tinker

`php artisan tinker` is the fastest way to poke at the system:

```php
// Create a Fincode customer for a user
$user = App\Models\User::factory()->create();
$svc = app(App\Services\CustomerSyncService::class);
$svc->ensureFincodeCustomer($user);

// Inspect Circuit Breaker state
$cb = app(App\Services\Fincode\CircuitBreaker::class);
$cb->getState();          // 'closed' | 'open' | 'half-open'
$cb->getFailureCount();   // current failure count

// Reset the breaker
$cb->reset();
```

## 9. IDE recommendations

- **PhpStorm**: enable Laravel + Pint + PHP CS Fixer plugins. Configure Pint as the formatter to match `composer test`.
- **VS Code**: install the **Laravel Extension Pack** and **ESLint** extensions. The `eslint.config.js` at the repo root drives JS/TS linting. (Prettier is not configured in this repo.)

## 10. Pre-commit hook (**required on first setup**)

Run this immediately after `composer setup`. It is the last line of defense against committing `.env` files or Fincode keys (`m_test_*` / `p_test_*`):

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit scripts/check-secrets.sh   # macOS / Linux only
```

On Windows, only the first line is needed. The hook runs `scripts/check-secrets.sh --staged` and blocks commits that contain `.env`, `credentials.json`, or strings matching common secret patterns (`AKIA*`, `sk_live_*`, `ghp_*`, etc.) with `exit 1`.

## Windows-specific notes

- Recommended shell: **Git Bash** (bundled with Git for Windows). Use Git Bash or WSL when running `composer dev` — PowerShell works, but `npx concurrently` colors and signal handling can get garbled.
- The hooks step does not need `chmod`. Just `git config core.hooksPath .githooks` is enough.
- File paths in `.env` should use forward slashes.

## Common issues

| Symptom | Likely cause |
| --- | --- |
| `SQLSTATE[HY000] [1045]` on first migrate | DB user / password in `.env` doesn't match what you created. |
| 419 Page Expired on form submit | Session is missing. Check `SESSION_DRIVER`. If using `database`, ensure `php artisan session:table && php artisan migrate` ran. |
| Vite build errors `Failed to resolve import` | `npm install` not run after pulling. |
| Fincode calls fail with `unauthorized` | Wrong key prefix (`m_prod_*` instead of `m_test_*`) or `FINCODE_BASE_URL` mismatched. |
| Queue events look like they're not firing | `composer dev` not running, or `QUEUE_CONNECTION=sync` (which executes synchronously and may swallow stack traces). |

## Where to read next

- [testing.md](./testing.md) — test setup and test DB.
- [fincode-setup.md](./fincode-setup.md) — Fincode account and test keys.
- [../architecture/overview.md](../architecture/overview.md) — how the layers fit together.
