English / [日本語](./index.ja.md)

# Customization guide

This template is a **starting point** — fork it and shape it into your product. This page maps out **what to change** and **what to leave alone**.

## Things you should change

These exist as placeholders. Replace them before deploying anything user-facing.

### Identity and branding

| Where | What | Notes |
| --- | --- | --- |
| `config/app.php` | `name` | Drives the page title, mail "from" name, etc. Or set `APP_NAME` in `.env`. |
| `package.json` | `name`, `description` | Shown on `npm` tooling output and in `package-lock.json`. |
| `composer.json` | `name`, `description`, `keywords`, `homepage`, `support`, `authors` | Currently set to `ltac0203-pixel/hatanaka`. Replace with your own owner/repo. |
| `README.md` / `README.ja.md` | Clone URL, repo references, organization name in copyright lines | Search the repo for `ltac0203-pixel/hatanaka` and replace with your own owner/repo. |
| `LICENSE` / `NOTICE` | Copyright holder, year if forking past 2026 | The license itself stays Apache-2.0. |
| `resources/js/Components/ApplicationLogo.tsx` | The default Laravel logo | Replace with your SVG / PNG. |
| `tailwind.config.js` | Theme colors | Configure your brand palette here so all components inherit it. |
| `resources/views/` (Blade), `resources/js/Pages/Auth/*` | Email templates, auth screen copy | Default Breeze copy is generic. |
| `lang/` | Translations | Default has `en` and `ja` skeletons. |

### Plans and pricing

Plans are managed entirely in the **Fincode management console**. The app fetches them at runtime and snapshots into the `subscriptions` row at sign-up time.

To add or change plans:

1. Open the Fincode admin → 定期課金 → プラン → 新規作成 (see [`fincode-setup.md`](../getting-started/fincode-setup.md)).
2. Copy the `plan_xxxxxx` ID.
3. (Optional) update the marketing copy / sort order in the frontend (`resources/js/Pages/Plan/*`).

You do **not** edit a `plans` table — there isn't one. See [`../architecture/data-model.md`](../architecture/data-model.md) for why.

### Feature surface

Out of the box you get registration, login, plan listing, card management, subscribe / cancel, and payment history. Trim or extend:

| To remove | Likely cleanup |
| --- | --- |
| Public registration | Restrict `routes/api.php` `register` and `routes/web.php` registration routes. |
| Self-service cancellation | Remove `DELETE /api/subscription` and the corresponding UI. |

| To add | Where to start |
| --- | --- |
| Email verification | Re-introduce `MustVerifyEmail` on `App\Models\User`, the `verified` middleware on protected routes, and the verify-email controllers. Use Laravel Breeze's scaffolding as a reference. |
| Multiple subscriptions per user | Drop the `subscriptions_active_user_id_unique` index ([data-model.md](../architecture/data-model.md)) and update `SubscriptionManager.subscribe`. |
| Coupons / proration | Implement at the Fincode side; surface in your `PlanService`. |
| Webhook-driven dunning | See [webhooks.md](./webhooks.md). |

## Things to think twice about

These are not "frozen" but they encode security or correctness decisions. Change them deliberately.

### Don't quietly disable

| Guard | Where | If you remove it… |
| --- | --- | --- |
| Sensitive log masking | `app/Services/Fincode/FincodeClient.php` | Card numbers / CVC / tokens may end up in `storage/logs/laravel.log`. |
| Idempotency-Key on Fincode mutations | `FincodeClient` | Network retries can double-charge or double-register cards. |
| `DB::transaction()` around state changes | `SubscriptionManager`, `CardManager` | Local DB and Fincode side can drift on partial failures. |
| Audit log writes | `AuditLogger` calls in managers | You lose forensic visibility for compliance. |
| `SecurityHeaders` middleware | `app/Http/Middleware/SecurityHeaders.php` | XSS, clickjacking, MIME sniffing risk increases. |
| Sanctum ability checks | `routes/api.php` (`ability:subscription:read` etc.) | A leaked token can do anything the user can. |
| Policies | `SubscriptionPolicy`, `CardPolicy` | Authorization regresses to "if it's in the DB, you can touch it." |
| Throttling | `routes/api.php` (`throttle:5,1`, `3,1`) | Brute force / abuse becomes trivial. |
| The `subscriptions_active_user_id_unique` index | migration `2026_02_21_010000` | Race conditions can create duplicate active subscriptions. |
| CSP report-only → enforce migration plan | `config/security.php` | If you stay in report-only forever, CSP doesn't actually defend anything. |

### Configure for your environment

| Knob | Default | When to tune |
| --- | --- | --- |
| `fincode.circuit_breaker.failure_threshold` (`config/fincode.php`) | 5 | Lower for traffic-sensitive apps; higher if you have flaky network paths. |
| `fincode.circuit_breaker.recovery_timeout` | 30 sec | Increase if Fincode incidents typically last longer. |
| `throttle:*` rates | per-route | Tune as your traffic profile becomes clear. |

## Things you can ignore

These are scaffolding that just works:

- `app/Http/Middleware/HandleInertiaRequests.php` — shared Inertia data; only touch when adding global props.
- `bootstrap/` — Laravel app bootstrapping; don't edit unless you know why.
- `database/migrations/0001_01_01_*` — Laravel framework tables (users, cache, jobs).
- `vendor/`, `node_modules/`, `public/build/` — generated. Do not commit (already in `.gitignore`).

## Where to read next

- [webhooks.md](./webhooks.md) — adding a Fincode webhook handler (not bundled).
- [../architecture/overview.md](../architecture/overview.md) — what each layer does, so you know what you're touching.
- [../operations/deployment.md](../operations/deployment.md) — checklist before going live.
