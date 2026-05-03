English / [日本語](./testing.ja.md)

# Testing

How to run tests, where they live, and how to write new ones without coupling them to live Fincode.

## Test stack

| Layer | Tool | Where |
| --- | --- | --- |
| PHP | PHPUnit 11 | `tests/Feature/`, `tests/Unit/` |
| JS / React | Vitest | `resources/js/**/*.test.ts(x)` |
| HTTP fakes | `Illuminate\Support\Facades\Http::fake()` | per test |

`phpunit.xml` defines two suites: `Unit` and `Feature`. Both run with:

```bash
composer test           # config:clear → artisan test (both suites)
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

## Test database

`phpunit.xml` pins `DB_CONNECTION=mysql` and `DB_DATABASE=hatanaka_testing` (along with charset/collation), so the test suite always runs against a dedicated `hatanaka_testing` database regardless of `.env`. Connection credentials still come from `.env.testing`:

```ini
# .env.testing
APP_ENV=testing
DB_CONNECTION=mysql
DB_USERNAME=app
DB_PASSWORD=change-me
```

Create the `hatanaka_testing` database once (see [local-development.md](./local-development.md) — "Create the database"). PHPUnit applies migrations through `RefreshDatabase` / `DatabaseMigrations` traits at the test level; you do not need to migrate manually.

> Tests target MariaDB / MySQL because the `subscriptions.active_user_id` virtual column relies on MySQL-specific generated-column syntax. SQLite cannot run the suite as-is.

## Suite layout

| Path | What lives there |
| --- | --- |
| `tests/Unit/` | Pure unit tests — no DB, no HTTP, no container side effects. Subdirectories: `Config`, `Enums`, `Exceptions`, `Jobs`, `Listeners`, `Models`, `Providers`, `Services`. |
| `tests/Feature/` | HTTP-level tests with the Laravel container booted. Subdirectories: `Api` (REST endpoints), `Auth` (login / register / verify), `Database` (migrations & schema invariants), `Requests` (FormRequest validation), `Web` (Inertia routes). Top-level files cover cross-cutting concerns: `ErrorPageTest`, `EventDiscoveryTest`, `ExceptionHandlerTest`, `PolicyTest`, `ProfileTest`, `SecurityTest`. |

When in doubt: if the test calls `$this->postJson(...)` or boots a route, it's a Feature test.

## Running a subset

```bash
# Run a single class
php artisan test --filter=SubscriptionStoreTest

# Run a single method
php artisan test --filter='SubscriptionStoreTest::test_user_with_active_subscription_gets_409'

# Stop on first failure
php artisan test --stop-on-failure

# Re-run only the last failed
php artisan test --filter=... --rerun
```

## How `composer test` works

```jsonc
"test": [
    "@php artisan config:clear --ansi",
    "@php artisan test"
]
```

`config:clear` is important: it removes any cached config from a previous run. Without it, `phpunit.xml` `<env>` overrides may not take effect, leading to "tests pass locally but fail on CI" puzzles.

## Frontend tests

```bash
npm run test        # interactive watch mode
npm run test:run    # one-shot, used in CI
```

Vitest config is in `vite.config.js`. Co-locate tests with the components they cover (`Foo.tsx` ↔ `Foo.test.tsx`).

## **Do not** call the real Fincode API in tests

Even though Fincode test mode is free, the test suite must not depend on it. Reasons:

1. **Reproducibility.** Test mode is shared infrastructure; latency and rate limits are unpredictable.
2. **Idempotency-Key bookkeeping.** A test re-run reusing the same Idempotency-Key would receive the cached prior response, hiding real test failures.
3. **CI does not have Fincode credentials.** Tests that require them are skipped or fail; both are bad signals.

Mock at one of these levels:

### A. `Http::fake()` — recommended for `FincodeClient` callers

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.test.fincode.jp/v1/customers' => Http::response([
        'id' => 'c_test_dummy',
        'name' => 'Test User',
    ], 200),
]);

$service = app(App\Services\Fincode\CustomerService::class);
$customer = $service->create($user);

Http::assertSent(fn ($req) => $req->url() === 'https://api.test.fincode.jp/v1/customers');
```

### B. Mock the Fincode service — when you don't care about HTTP details

```php
$this->mock(App\Services\Fincode\CardService::class)
    ->shouldReceive('createCard')
    ->once()
    ->andReturn(new App\Services\Fincode\CardDto(...));
```

Use this when testing `CardManager` or `SubscriptionManager` and only the orchestration matters.

### C. Test doubles for the Circuit Breaker

`CircuitBreaker` reads from the cache. In `phpunit.xml`, `CACHE_STORE=array`, so each test starts with a clean breaker. To force a state:

```php
Cache::store()->put('fincode_circuit_breaker:state', 'open', 300);
Cache::store()->put('fincode_circuit_breaker:opened_at', time(), 300);
```

Then assert the call raises `CircuitBreakerOpenException`.

## Coverage

```bash
php artisan test --coverage           # text summary
php artisan test --coverage-html=coverage   # full HTML report
```

CI enforces a **50% statement coverage threshold** (see `.github/workflows/ci.yml`). Pull requests that drop coverage below 50% fail the `Check coverage threshold` step. Locally, `--coverage-clover=coverage.xml` produces the same metric CI computes.

## Writing good tests for this project

- **Test the public surface.** `tests/Feature/Api/*` should drive `routes/api.php` — not call manager methods directly.
- **Use factories, not seeders.** Seeders are for human-facing data; factories give isolated, intention-revealing fixtures.
- **Reset state between tests.** Use `RefreshDatabase`; do not rely on commit between tests.
- **Assert on the audit log.** State changes should produce `audit_logs` rows. Test that they do.

```php
$this->assertDatabaseHas('audit_logs', [
    'event' => 'subscription.created',
    'auditable_type' => Subscription::class,
    'user_id' => $user->id,
]);
```

- **Don't assert log strings.** They change; assert behavior, not text.

## Where to read next

- [local-development.md](./local-development.md) — DB setup.
- [../architecture/error-handling.md](../architecture/error-handling.md) — exception → HTTP status mapping that Feature tests rely on.
- [../architecture/data-model.md](../architecture/data-model.md) — what's in the DB after a successful operation.
