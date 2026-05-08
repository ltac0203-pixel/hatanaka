English / [日本語](./README.ja.md)

# docs — Documentation index

This directory contains all the specification and operations documents for hatanaka (the Fincode subscription reference implementation). The intended OSS journey is **"clone → run locally → integrate into your own service"** — start here.

> For a high-level project overview, see the [top-level README.md](../README.md).

## Getting started (getting-started/)

The shortest path right after cloning. Read these in order to get the app running locally.

| Document | Contents |
| --- | --- |
| [getting-started/local-development.md](./getting-started/local-development.md) | PHP / Node / DB setup, `composer setup`, running `composer dev`, troubleshooting |
| [getting-started/fincode-setup.md](./getting-started/fincode-setup.md) | Creating a Fincode test account, obtaining `m_test_*` / `p_test_*` keys, test cards |
| [getting-started/testing.md](./getting-started/testing.md) | Running PHPUnit / Vitest, test database, Fincode mocking strategy |

## Architecture (architecture/)

Design notes that explain *why* things are shaped the way they are.

| Document | Contents |
| --- | --- |
| [architecture/overview.md](./architecture/overview.md) | Layer responsibilities, sequence diagrams for card registration / subscription, queue usage |
| [architecture/data-model.md](./architecture/data-model.md) | ER diagram, design intent per table, soft-delete and foreign-key behavior |
| [architecture/error-handling.md](./architecture/error-handling.md) | Exception hierarchy, circuit breaker, HTTP status mapping, retry policy |
| [architecture/commit-guidelines.md](./architecture/commit-guidelines.md) | Commit granularity and prefix conventions |

## API reference (api/)

| Document | Contents |
| --- | --- |
| [api/README.md](./api/README.md) | Authentication, endpoint summary, error format, Fincode relationship |
| [api/openapi.yml](./api/openapi.yml) | OpenAPI 3.0.3 spec (preview with Redocly / Swagger UI) |

## Customization (customization/)

What to change and what to keep frozen when adopting this OSS in your own service.

| Document | Contents |
| --- | --- |
| [customization/index.md](./customization/index.md) | Editable vs. frozen surface area, removal / extension guidance |
| [customization/webhooks.md](./customization/webhooks.md) | Why webhooks are not bundled and how to add them |

## Operations (operations/)

This OSS does not ship automated deployment; the documents below are **optional references** for self-hosted deployments.

| Document | Contents |
| --- | --- |
| [operations/deployment.md](./operations/deployment.md) | Production checklist, Nginx / Supervisor examples for self-hosted environments |
| [operations/api-token-rotation.md](./operations/api-token-rotation.md) | Sanctum token lifetime, abilities, rotation operations |
| [operations/password-reset.md](./operations/password-reset.md) | Why standard password reset is intentionally removed and how to restore it |

## Repository-root references

- [../README.md](../README.md) — Project overview and quickstart
- [../CONTRIBUTING.md](../CONTRIBUTING.md) — How to contribute
- [../SECURITY.md](../SECURITY.md) — Vulnerability reporting
- [../CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) — Code of conduct
- [../CHANGELOG.md](../CHANGELOG.md) — Release notes
- [../LICENSE](../LICENSE) — Apache-2.0 license
