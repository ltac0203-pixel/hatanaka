English / [日本語](./README.ja.md)

# hatanaka

**Reference implementation of a subscription management web application integrated with the Fincode payment API.**

A sample project built with Laravel 12 + React 19 + Inertia.js + TypeScript that demonstrates how to implement subscription billing using [Fincode](https://www.fincode.jp/). Use it as a starting point for building recurring billing systems on Fincode.

> Fincode is a Japanese payment gateway. This project is intended for developers building services on the Japanese market who want a working reference for Fincode integration.

## Features

### Authentication

- User registration / login / logout
- Email verification

### Subscription management

- Plan listing and detail view
- Credit card add / list / delete (using Fincode tokenization — full PAN never reaches the application server)
- Subscribe / cancel a plan
- Payment history

## Tech stack

### Frontend

- **Framework**: React 19 + Inertia.js
- **Build tool**: Vite
- **Language**: TypeScript
- **Styling**: Tailwind CSS

### Backend

- **Framework**: Laravel 12
- **Authentication**: Laravel Breeze + Sanctum
- **Database**: MySQL
- **Payment gateway**: Fincode API

> Found a security issue? Please follow [SECURITY.md](./SECURITY.md) — do not open a public issue.

## Getting started

### Requirements

- PHP 8.3+
- Node.js v22+
- Composer
- MySQL 8.0+ or MariaDB
- A Fincode account (test mode is sufficient)

> **Recommended toolchain**: Git Bash (on Windows) + Volta + Mailpit + an active pre-commit hook. All free OSS. See [docs/getting-started/local-development.md](./docs/getting-started/local-development.md#0-recommended-toolchain) for details.

### 1. Clone and install

```bash
git clone https://github.com/ltac0203-pixel/hatanaka.git
cd hatanaka

# Install deps, copy .env, generate APP_KEY, build assets (no migrations yet)
composer setup
```

### 2. Configure environment variables

Edit `.env` and set the following:

```ini
# Database
DB_HOST=127.0.0.1
DB_DATABASE=subscription_app
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Fincode Configuration
FINCODE_API_KEY=m_test_...
FINCODE_PUBLIC_KEY=p_test_...
FINCODE_BASE_URL=https://api.test.fincode.jp
```

Obtain `FINCODE_API_KEY` and `FINCODE_PUBLIC_KEY` from the Fincode management console. Test keys are prefixed with `m_test_` / `p_test_`. Set `FINCODE_BASE_URL` to `https://api.fincode.jp` for production or `https://api.test.fincode.jp` for testing.

### 3. Run migrations

After the database is created and `.env` is configured, run migrations:

```bash
composer setup:db
```

This is split from `composer setup` because migrations require valid DB credentials, which you set in step 2.

See [docs/getting-started/fincode-setup.md](./docs/getting-started/fincode-setup.md) for step-by-step Fincode account setup. The Fincode developer documentation is primarily in Japanese — see [docs.fincode.jp](https://docs.fincode.jp/).

#### Enable the pre-commit hook (recommended)

```bash
git config core.hooksPath .githooks

# macOS / Linux
chmod +x .githooks/pre-commit scripts/check-secrets.sh
```

The hook runs `scripts/check-secrets.sh --staged` to detect accidentally staged `.env` files and API key patterns.
On Windows where `chmod` is unavailable, only `git config core.hooksPath .githooks` is required.

### 4. Run the application

```bash
# Run Laravel + Vite + Queue concurrently
composer dev
```

Open `http://localhost:8000` in your browser.

## Command reference

```bash
# Development
composer dev          # Start dev server
composer test         # Run PHP tests
./vendor/bin/pint     # PHP linter

# Frontend
npm run dev           # Vite dev server
npm run build         # Production build
npm run lint          # ESLint
npm run test:run      # Vitest

# Database
php artisan migrate              # Run migrations
php artisan migrate:fresh --seed # Reset and reseed
```

## API endpoints

### Auth

| Method | Endpoint              | Description           |
| ------ | --------------------- | --------------------- |
| POST   | `/api/register`       | Register a user       |
| POST   | `/api/login`          | Log in                |
| POST   | `/api/logout`         | Log out               |
| GET    | `/api/user`           | Current user          |
| GET    | `/api/session-status` | Session validity      |

### Subscription

| Method | Endpoint                           | Description           |
| ------ | ---------------------------------- | --------------------- |
| GET    | `/api/subscription`                | Current subscription  |
| POST   | `/api/subscription`                | Subscribe             |
| DELETE | `/api/subscription`                | Cancel subscription   |
| GET    | `/api/subscription/history`        | Payment history       |
| GET    | `/api/subscription/plans`          | List plans            |
| GET    | `/api/subscription/cards`          | List saved cards      |
| POST   | `/api/subscription/cards`          | Add a card            |
| DELETE | `/api/subscription/cards/{cardId}` | Delete a card         |

The full OpenAPI spec is at [docs/api/openapi.yml](./docs/api/openapi.yml).

## Project layout

```
hatanaka/
├── app/
│   ├── Http/Controllers/    # HTTP controllers
│   ├── Http/Resources/      # API response formatters
│   ├── Models/              # Eloquent models
│   ├── Policies/            # Authorization policies
│   └── Services/            # Business logic
│       └── Fincode/         # Fincode API client & services
├── config/
│   └── fincode.php          # Fincode configuration
├── database/
│   ├── migrations/          # DB migrations
│   └── seeders/             # Sample data
├── resources/
│   └── js/
│       ├── Pages/           # Inertia.js pages
│       ├── Components/      # UI components
│       └── types/           # TypeScript types
├── routes/
│   ├── web.php              # Web routes
│   └── api.php              # API routes
└── tests/                   # Test cases
```

## Documentation

| Topic | Document |
| --- | --- |
| Getting started | [docs/getting-started/fincode-setup.md](./docs/getting-started/fincode-setup.md), [local-development.md](./docs/getting-started/local-development.md), [testing.md](./docs/getting-started/testing.md) |
| Architecture | [overview.md](./docs/architecture/overview.md), [data-model.md](./docs/architecture/data-model.md), [error-handling.md](./docs/architecture/error-handling.md), [commit-guidelines.md](./docs/architecture/commit-guidelines.md) |
| API | [docs/api/README.md](./docs/api/README.md) ([openapi.yml](./docs/api/openapi.yml)) |
| Operations (optional) | [deployment.md](./docs/operations/deployment.md) — reference for hosting this code yourself; the project is primarily intended as a reference implementation |
| Customization (using this as a template) | [customization/index.md](./docs/customization/index.md), [webhooks.md](./docs/customization/webhooks.md) |
| Project policies | [CONTRIBUTING.md](./CONTRIBUTING.md), [SECURITY.md](./SECURITY.md) |

## Contributing

Pull requests and issues are welcome. See [CONTRIBUTING.md](./CONTRIBUTING.md) for the workflow ([GitHub Flow](https://docs.github.com/en/get-started/quickstart/github-flow): branch off `main`, open a PR targeting `main`) and [docs/architecture/commit-guidelines.md](./docs/architecture/commit-guidelines.md) for commit conventions.

## License

[Apache License 2.0](./LICENSE)

Copyright 2026 hatanaka contributors
