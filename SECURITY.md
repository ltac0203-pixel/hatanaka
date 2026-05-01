English / [日本語](./SECURITY.ja.md)

# Security Policy

This project handles a payment integration. We take security reports seriously.

## Reporting a vulnerability

**Do not file a public GitHub issue for security vulnerabilities.**

Use **GitHub Private Security Advisories**: open a draft advisory at the **Security** tab of the repository.

Include:

- A description of the issue and the affected file paths or endpoints.
- A proof-of-concept or steps to reproduce.
- Your assessment of impact (data exposure, privilege escalation, etc.).
- Whether the issue is exploitable in the default configuration or requires a misconfiguration.

Please give us a reasonable time to investigate and fix before public disclosure.

### Response targets

| Stage | Target |
| --- | --- |
| Acknowledgement | within 3 business days |
| Initial assessment | within 7 business days |
| Fix or mitigation plan | depends on severity |

These are targets for a small open-source project, not contractual SLAs.

## Supported versions

Only the **latest `main`** is actively maintained. There are no LTS branches. Forks are responsible for tracking upstream fixes.

## Out of scope

The following are not considered vulnerabilities of this template:

- Issues that require a misconfigured production environment (e.g. `APP_DEBUG=true` in production).
- Denial-of-service via volumetric attacks against the application server.
- Vulnerabilities in third-party dependencies that have no security advisory yet — please report those upstream.
- Reports based on automated scanner output without a working PoC.

## Scope of this template's security stance

This template makes the following design choices to reduce the security surface. **If you fork it, do not regress these without understanding the consequence.**

| Control | Where | What it prevents |
| --- | --- | --- |
| Card tokenization in the browser (Fincode JS) | `resources/js/Pages/Card/*` | Full PAN / CVC never reach the application server. PCI scope is reduced. |
| Sensitive log masking | `app/Services/Fincode/FincodeClient.php` | Card numbers, CVC, and tokens are masked before being written to logs. |
| Idempotency-Key on every Fincode mutation | `FincodeClient` | Network retries do not produce duplicate charges or duplicate cards. |
| Circuit breaker | `app/Services/Fincode/CircuitBreaker.php` | Cascading failures during Fincode outages are avoided; the app fails fast instead of holding HTTP connections. |
| Audit log | `app/Services/AuditLogger.php`, `audit_logs` table | Every state-changing operation is recorded with before / after values, IP, and user agent. |
| `DB::transaction()` around state changes | `SubscriptionManager`, `CardManager` | Local DB and Fincode side stay consistent; on failure, local rows are rolled back. |
| Sanctum + ability tokens | `routes/api.php` (`ability:subscription:read` / `:write`, `ability:card:read` / `:write`) | Compromised tokens have a narrow blast radius. |
| Policies | `app/Policies/SubscriptionPolicy.php`, `CardPolicy.php` | Users can only operate on their own subscriptions / cards (defense in depth, on top of where-clauses). |
| Throttling | `routes/api.php` (`throttle:5,1` for auth, `3,1` for subscribe / card add) | Brute force and resource exhaustion. |
| Security headers + CSP | `app/Http/Middleware/SecurityHeaders.php`, `config/security.php` | XSS, clickjacking, MIME sniffing. CSP violations are logged via `POST /api/security/csp-reports`. |
| Soft deletes | Subscriptions / Cards / Customers | Audit trail survives "deletion". |

### What this template does **not** ship

- A Fincode Webhook handler. If you wire one up, **verify signatures** and **enforce idempotency** — see [docs/customization/webhooks.md](./docs/customization/webhooks.md).
- Two-factor authentication for end users.
- A cron-driven dunning / retry workflow for failed charges.

## Researcher conduct

When testing, do not:

- Access data belonging to other users.
- Run destructive payloads against shared environments.
- Pivot beyond what is needed to demonstrate the issue.
- Publicly disclose the vulnerability before a fix is released or 90 days have passed, whichever is earlier.

We will acknowledge reporters who follow responsible disclosure in release notes if they wish.

## License

This template is distributed under the [Apache License 2.0](./LICENSE).
