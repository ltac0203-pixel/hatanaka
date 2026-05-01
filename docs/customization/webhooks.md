English / [日本語](./webhooks.ja.md)

# Fincode webhook integration

> **This template does not ship a webhook handler.** It documents the design you should follow if you add one.

## Why webhooks matter

The synchronous flow (subscribe → Fincode creates the subscription → user sees success) covers the **happy path of contract creation**. Recurring billing afterwards is asynchronous: Fincode charges the card on schedule and reports the result. Without webhooks, your `subscription_results` table never learns about those charges; you only see them in the Fincode dashboard.

Wire up webhooks if you need any of:

- Showing accurate "last payment status" in the user's billing history.
- Reacting to failed charges (dunning, status flip to `unpaid`).
- Triggering downstream provisioning (entitlements, feature flags) on charge success.

## Endpoint design

Add a controller and route. Suggested layout:

```
app/Http/Controllers/Api/Webhooks/FincodeWebhookController.php
routes/api.php   →  POST /api/webhooks/fincode
```

The route must be **outside** the `auth:sanctum` middleware group — webhooks come from Fincode, not from your authenticated users.

```php
// routes/api.php
Route::post('/webhooks/fincode', App\Http\Controllers\Api\Webhooks\FincodeWebhookController::class)
    ->middleware(['throttle:120,1'])  // generous; Fincode retries
    ->name('api.webhooks.fincode');
```

Be careful when caching routes (`php artisan route:cache`) — closures cannot be cached, so define the controller as a class.

## Signature verification (mandatory)

**An unverified webhook endpoint is an unauthenticated POST endpoint that mutates billing state.** Anyone who knows the URL can fake a charge result.

Fincode sends a signature header (consult the current Fincode docs for the exact header name and HMAC algorithm — this changes between API versions). Verify it before doing anything else:

```php
public function __invoke(Request $request): Response
{
    $signature = $request->header('Fincode-Signature');
    $payload = $request->getContent();
    $secret = config('fincode.webhook_secret');

    $expected = hash_hmac('sha256', $payload, $secret);

    if (! hash_equals($expected, $signature ?? '')) {
        abort(401);
    }

    // ... handle the event
}
```

Store `FINCODE_WEBHOOK_SECRET` in `.env` and add it to `config/fincode.php`. Rotate it on the Fincode side, then in your env, in that order.

## Idempotency (mandatory)

Webhooks are **at-least-once**. Fincode retries on non-2xx responses, on timeouts, and sometimes for reasons that don't fit either category. Your handler must produce the same result on the second delivery as on the first.

Two reliable approaches:

### A. Dedupe on the Fincode-supplied event ID

Most webhook systems include a unique event ID. Persist it:

```php
DB::table('webhook_events_seen')->insertOrIgnore([
    'event_id' => $event->id,
    'created_at' => now(),
]);

if (DB::table('webhook_events_seen')->where('event_id', $event->id)->exists()) {
    // first time we've seen it — process
} else {
    // duplicate — return 200 OK and stop
    return response()->noContent();
}
```

### B. Upsert into `subscription_results`

If your handler writes to `subscription_results`, key the upsert on `(fincode_subscription_id, fincode_payment_id)`:

```php
SubscriptionResult::updateOrCreate(
    [
        'fincode_subscription_id' => $event->subscription_id,
        'fincode_payment_id' => $event->payment_id,
    ],
    [
        'subscription_id' => $subscription->id,
        'user_id' => $subscription->user_id,
        'status' => $event->status,
        'amount' => $event->amount,
        'charged_at_date' => $event->charged_at_date,
        'fincode_response' => $event->raw,
    ],
);
```

This is naturally idempotent: a duplicate webhook just rewrites the same row.

## Decoupling from the synchronous code path

Treat webhook handlers as **independent consumers** that share the persistence layer with the rest of the app. Do not call `SubscriptionManager.subscribe` from inside the handler — that method is built for the synchronous flow with its own audit / event semantics. Instead:

1. The handler updates persistence (`subscription_results`, possibly `subscriptions.status`).
2. It writes an audit log row with `user_id = NULL` (system-initiated). See [`../architecture/data-model.md`](../architecture/data-model.md).
3. It dispatches a domain event (e.g. `SubscriptionStatusChanged`) — the same event your synchronous flow uses, so listeners need only one path.

This keeps the synchronous and asynchronous flows symmetric in their downstream effects (notifications, audit log, etc.) while letting each side own its own validation.

## Retry / DLQ

Return:

- `200 OK` (or `204 No Content`) when you've durably persisted the event — Fincode stops retrying.
- `4xx` for permanent failures (bad signature, malformed payload). **Do not** retry these.
- `5xx` (or just throw) for transient failures (DB down, etc.). Fincode retries.

For events you can't process (unknown subscription ID, etc.), persist them to a "dead letter" table for manual triage rather than returning 4xx in a loop:

```php
DB::table('webhook_events_dead_letters')->insert([
    'event_id' => $event->id,
    'payload' => json_encode($event->raw),
    'reason' => 'unknown subscription_id',
    'created_at' => now(),
]);
return response()->noContent();
```

## Local testing

Fincode cannot reach `localhost`. Use a tunnel:

```bash
# ngrok
ngrok http 8000
# → use https://xxxx.ngrok-free.app/api/webhooks/fincode in Fincode's webhook config
```

Or **fake the webhook locally**:

```bash
curl -X POST http://localhost:8000/api/webhooks/fincode \
  -H 'Fincode-Signature: '"$(printf '%s' "$payload" | openssl dgst -sha256 -hmac "$secret" | awk '{print $2}')" \
  -H 'Content-Type: application/json' \
  -d "$payload"
```

Add a Feature test that drives the route with a known signature and asserts the resulting `subscription_results` row.

## Where to read next

- [`../architecture/data-model.md`](../architecture/data-model.md) — `subscription_results` and `audit_logs` schemas.
- [`../architecture/error-handling.md`](../architecture/error-handling.md) — failure modes you'll inherit from `FincodeClient` if your handler calls back into Fincode.
- [`../getting-started/fincode-setup.md`](../getting-started/fincode-setup.md) — where to register the webhook URL in the Fincode console.
