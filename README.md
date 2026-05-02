English / [日本語](./README.ja.md)

# hatanaka

**Reference implementation of a subscription management web application integrated with the Fincode payment API.**

A sample project built with Laravel 12 + React 19 + Inertia.js + TypeScript that demonstrates how to implement subscription billing using [Fincode](https://www.fincode.jp/). Use it as a starting point for building recurring billing systems on Fincode.

> Fincode is a Japanese payment gateway. This project is intended for developers building services on the Japanese market who want a working reference for Fincode integration.

## Features

### Authentication

- User registration / login / logout

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
- Docker Desktop (recommended path) — or MySQL 8.0+ / MariaDB 10.6+ (manual path)
- A Fincode account (test mode is sufficient)

You can verify registration / login flows without Fincode keys; reaching the checkout screen requires `m_test_*` / `p_test_*` keys. See [docs/getting-started/fincode-setup.md](./docs/getting-started/fincode-setup.md) to obtain them. The Fincode developer documentation is primarily in Japanese — see [docs.fincode.jp](https://docs.fincode.jp/).

### Quick start (Docker, recommended)

The included Compose file boots MySQL and Mailpit, so you don't need a local MySQL.

```bash
git clone https://github.com/ltac0203-pixel/hatanaka.git
cd hatanaka

composer setup            # Install deps, copy .env, generate APP_KEY, build assets
docker compose up -d      # Start MySQL (:3307) and Mailpit (:8025)
```

Edit `.env` to point at the Docker DB and set Fincode keys:

```ini
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=hatanaka
DB_USERNAME=hatanaka
DB_PASSWORD=hatanaka

FINCODE_API_KEY=m_test_...
FINCODE_PUBLIC_KEY=p_test_...
FINCODE_BASE_URL=https://api.test.fincode.jp

# Optional: route mail to Mailpit
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

```bash
composer setup:db         # Run migrations
composer dev              # Run Laravel + Vite + Queue concurrently
```

| Service | URL |
| --- | --- |
| App | http://localhost:8000 |
| Mailpit (mail UI) | http://localhost:8025 |

### Manual setup (local MySQL)

If you'd rather not use Docker. See [docs/getting-started/local-development.md](./docs/getting-started/local-development.md) for the full guide.

1. Create a database and user in MySQL
    ```sql
    CREATE DATABASE subscription_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'app'@'localhost' IDENTIFIED BY 'change-me';
    GRANT ALL ON subscription_app.* TO 'app'@'localhost';
    ```
2. `composer setup`
3. Edit `.env` (DB credentials and Fincode keys)
4. `composer setup:db`
5. `composer dev` → http://localhost:8000

### Enable the pre-commit hook (recommended)

```bash
git config core.hooksPath .githooks

# macOS / Linux only
chmod +x .githooks/pre-commit scripts/check-secrets.sh
```

The hook runs `scripts/check-secrets.sh --staged` to detect accidentally staged `.env` files and API key patterns. On Windows where `chmod` is unavailable, only the first line is required.

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
