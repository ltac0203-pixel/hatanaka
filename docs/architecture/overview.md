English / [日本語](./overview.ja.md)

# Architecture overview

This document describes how a request flows through the application, why the layers exist, and where each responsibility lives.

## High-level diagram

```mermaid
flowchart LR
    Browser["Browser (React + Inertia.js)"]
    Controllers["app/Http/Controllers/Api/*"]
    Managers["Service layer<br/>SubscriptionManager / CardManager / CustomerSyncService<br/>AuditLogger"]
    Fincode["app/Services/Fincode/*<br/>FincodeClient + CustomerService / CardService<br/>SubscriptionService / PlanService"]
    Breaker["CircuitBreaker"]
    DB[("MySQL<br/>users / fincode_customers / fincode_cards<br/>subscriptions / subscription_results / audit_logs")]
    FincodeApi(("Fincode API"))
    Queue["Queue worker (events / listeners)"]

    Browser -->|Inertia / fetch| Controllers
    Controllers --> Managers
    Managers --> DB
    Managers --> Fincode
    Fincode --> Breaker
    Fincode --> FincodeApi
    Managers -. dispatches events .-> Queue
    Queue --> DB
```

The browser does **not** call the Fincode API directly except for **card tokenization** (Fincode JS in the browser produces a single-use token; the token is what the server receives).

## Layer responsibilities

| Layer | Directory | Responsibility | What it must NOT do |
| --- | --- | --- | --- |
| Controller | `app/Http/Controllers/` | Validate input (FormRequest), authorize (Policy), call a single Service / Manager method, format response (Resource) | Talk to Fincode API. Run business logic. Use the DB facade directly for writes. |
| Manager | `app/Services/SubscriptionManager.php`, `CardManager.php`, `CustomerSyncService.php` | Orchestrate one business operation. Wrap state changes in `DB::transaction()`. Emit events. Write audit logs. | Construct HTTP requests. Know about Fincode response shapes. |
| Fincode service | `app/Services/Fincode/CustomerService.php`, `CardService.php`, `SubscriptionService.php`, `PlanService.php` | Translate domain calls into Fincode API calls. Map Fincode response into typed return values or domain exceptions. | Touch the local DB. Care about Eloquent models. |
| Fincode client | `app/Services/Fincode/FincodeClient.php` | Bearer auth, Idempotency-Key, retries, sensitive log masking, Circuit Breaker integration. | Implement business semantics. |
| Resource | `app/Http/Resources/` | Shape JSON for the API response. Strip / mask anything sensitive. | Run queries. |
| Policy | `app/Policies/` | Ownership check (`$user->id === $card->user_id`, etc.). | Throw business exceptions. |

## Why Inertia.js?

The app uses Inertia.js to share the same Laravel routes for both server-rendered pages and SPA navigation. This avoids maintaining a separate REST contract for the web UI; the same controllers serve `Inertia::render(...)` to the browser. The dedicated REST API (`routes/api.php`, Sanctum-protected) exists for **external clients** (mobile, third-party integrations) and is the surface documented in [docs/api/openapi.yml](../api/openapi.yml).

## Sequence: card registration

```mermaid
sequenceDiagram
    autonumber
    participant U as User (Browser)
    participant FJS as Fincode JS
    participant L as Laravel<br/>(CardController)
    participant CM as CardManager
    participant CSS as CustomerSyncService
    participant CS as CardService
    participant FC as FincodeClient
    participant FA as Fincode API
    participant AL as AuditLogger
    participant DB as MySQL

    U->>FJS: enter PAN / exp / CVC
    FJS->>FA: tokenize (browser ↔ Fincode direct)
    FA-->>FJS: card token
    FJS-->>U: token
    U->>L: POST /api/subscription/cards { token }
    Note over L: FormRequest validates,<br/>CardPolicy authorizes
    L->>CM: register(user, token)
    CM->>DB: BEGIN TRANSACTION
    CM->>CSS: ensureFincodeCustomer(user)
    CSS->>FC: POST /v1/customers (idempotency-key)
    FC->>FA: POST /v1/customers
    FA-->>FC: customer
    FC-->>CSS: customer
    CSS->>DB: upsert fincode_customers
    CM->>CS: createCard(customerId, token)
    CS->>FC: POST /v1/customers/.../cards (idempotency-key)
    FC->>FA: POST /v1/customers/.../cards
    FA-->>FC: card
    FC-->>CS: card
    CM->>DB: insert fincode_cards
    CM->>AL: log("card.registered", before, after)
    AL->>DB: insert audit_logs
    CM->>DB: COMMIT
    CM-->>L: FincodeCard
    L-->>U: 201 Created (CardResource)
```

Key invariants:

- **Full PAN / CVC never reaches Laravel.** Only the Fincode token does.
- The Idempotency-Key is generated **once per request** and reused on retries inside `FincodeClient`. Network retries do not register the card twice on Fincode.
- If the Fincode call succeeds but the local insert fails, the transaction rolls back the local row but **the Fincode-side card already exists**. The next request from the same user will reuse the existing customer (via `CustomerSyncService.ensureFincodeCustomer`); orphaned Fincode cards can be reconciled by background tooling.

## Sequence: subscription creation

```mermaid
sequenceDiagram
    autonumber
    participant U as User
    participant L as Laravel<br/>(SubscriptionController)
    participant SM as SubscriptionManager
    participant PS as PlanService
    participant SS as SubscriptionService
    participant FC as FincodeClient
    participant FA as Fincode API
    participant AL as AuditLogger
    participant DB as MySQL
    participant Q as Queue (events)

    U->>L: POST /api/subscription { plan_id, card_id }
    Note over L: throttle:3,1<br/>ability:subscription:write<br/>SubscriptionPolicy
    L->>SM: subscribe(user, planId, cardId)
    SM->>DB: SELECT active subscription (active_user_id unique index)
    alt already active
        SM-->>L: throw ActiveSubscriptionExistsException
        L-->>U: 409 Conflict
    end
    SM->>PS: fetch(planId)
    PS->>FC: GET /v1/plans/{planId}
    FC->>FA: GET
    FA-->>FC: plan
    SM->>DB: BEGIN TRANSACTION
    SM->>SS: create(customerId, cardId, plan)
    SS->>FC: POST /v1/subscriptions (idempotency-key)
    FC->>FA: POST
    FA-->>FC: subscription
    SM->>DB: insert subscriptions (with plan snapshot)
    SM->>AL: log("subscription.created")
    SM->>DB: COMMIT
    SM->>Q: dispatch(SubscriptionCreated event)
    SM-->>L: Subscription
    L-->>U: 201 Created (SubscriptionResource)
```

Key invariants:

- **One active subscription per user** is enforced both by application logic (manager-level check) and by the DB-level unique index `subscriptions_active_user_id_unique` on the virtual column `active_user_id`. Even a race condition cannot create a duplicate.
- **Plan data is snapshotted into the `subscriptions` row at creation time** (`plan_name`, `plan_amount`, `plan_interval`, `plan_snapshot` JSON). After this, the subscription does not depend on a separate `plans` table — see [data-model.md](./data-model.md) for the rationale.
- Events (`SubscriptionCreated`, `SubscriptionStatusChanged`, etc.) are dispatched after the transaction commits and processed by the queue worker for side effects (notifications, downstream sync).

## Where the queue is used

`composer dev` runs `php artisan queue:listen` in addition to the web server. The queue handles:

- Audit log persistence triggered by events (in `app/Listeners/`).
- Email notifications (registration, email verification).
- Downstream side effects of subscription / card events.

In production, run a long-lived `queue:work` under Supervisor — see [docs/operations/deployment.md](../operations/deployment.md).

## Where to read next

- [data-model.md](./data-model.md) — schema and relationships.
- [error-handling.md](./error-handling.md) — exception hierarchy, Circuit Breaker, retry policy.
- [../getting-started/local-development.md](../getting-started/local-development.md) — run it locally.
- [../api/openapi.yml](../api/openapi.yml) — REST API contract.
