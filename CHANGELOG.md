English / [日本語](./CHANGELOG.ja.md)

# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

While the project is on `0.x`, minor versions may include breaking changes. See [SECURITY.md](./SECURITY.md) for the supported-versions policy.

## [Unreleased]

## [0.1.0] - 2026-05-01

Initial public release as an open-source reference implementation.

### Added

- Subscription management application built on Laravel 12 + React 19 + Inertia.js + TypeScript.
- Fincode payment integration:
  - `FincodeClient` HTTP client with Bearer auth, idempotency keys, and sensitive-data masking in logs.
  - Customer / Card / Subscription services wrapping Fincode CRUD operations.
  - Card tokenization on the frontend via Fincode JS — full PAN never reaches the application server.
- Authentication via Laravel Breeze + Sanctum: registration, login/logout, password reset, email verification.
- Subscription features: plan listing/detail, card add/list/delete, subscribe/cancel, payment history.
- REST API under `/api/*` with OpenAPI spec at `docs/api/openapi.yml`.
- Audit log of state changes (`audit_logs`) with before/after values.
- Soft deletes on `plans`, `subscriptions`, `cards`.
- Authorization policies (`SubscriptionPolicy`, `CardPolicy`).
- Security hardening: CSP report endpoint, security headers middleware, rate limiting.
- Documentation: getting started, architecture, API, operations, customization (English + Japanese).
- Project policies: `LICENSE` (Apache-2.0), `NOTICE`, `CONTRIBUTING.md`, `SECURITY.md`.
- Tooling: Pint (PHP), ESLint (TypeScript), PHPUnit 11, Vitest, pre-commit secret scanner.
- GitHub Actions CI: secrets guard, PHP build/lint/test with coverage threshold (50%), auto Draft PR for `feature/*` branches.

[Unreleased]: https://github.com/ltac0203-pixel/hatanaka/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ltac0203-pixel/hatanaka/releases/tag/v0.1.0
