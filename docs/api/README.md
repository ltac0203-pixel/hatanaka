# docs/api — API Reference

Japanese version: [README.ja.md](./README.ja.md)

## Files

| File                         | Description                                                                          |
| ---------------------------- | ------------------------------------------------------------------------------------ |
| [openapi.yml](./openapi.yml) | OpenAPI 3.0.3 specification for **this app's REST API** (13 endpoints in total)      |

> **Important:** `openapi.yml` describes the API this app exposes to external clients.
> The Fincode API is called only from the server side — never directly from the frontend.

---

## Authentication

The API supports two authentication modes via [Laravel Sanctum](https://laravel.com/docs/sanctum).

### Session authentication (recommended for web browsers)

A session cookie is issued after `POST /login` and is sent automatically by the browser.

```http
POST /api/login
Content-Type: application/json

{ "email": "user@example.com", "password": "password123" }
```

> The `X-XSRF-TOKEN` header is required for CSRF protection.

### Bearer-token authentication (mobile / external clients)

Include `device_name` in `POST /login` to receive a token in the response.

```http
POST /api/login
Content-Type: application/json

{ "email": "user@example.com", "password": "password123", "device_name": "MyApp-iPhone" }
```

Response:

```json
{
  "user": { ... },
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

Attach the token on subsequent requests:

```http
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## Endpoint summary

| Method | Path                             | Auth     | Rate limit | Description                                  |
| ------ | -------------------------------- | -------- | ---------- | -------------------------------------------- |
| POST   | `/api/register`                  | None     | 5/min      | Register a user                              |
| POST   | `/api/login`                     | None     | 5/min      | Log in                                       |
| GET    | `/api/session-status`            | None     | -          | Check authentication state                   |
| POST   | `/api/logout`                    | Required | -          | Log out                                      |
| GET    | `/api/user`                      | Required | -          | Get the authenticated user                   |
| GET    | `/api/subscription`              | Required | -          | Get the active subscription                  |
| POST   | `/api/subscription`              | Required | 3/min      | Create a subscription                        |
| DELETE | `/api/subscription`              | Required | -          | Cancel the subscription                      |
| GET    | `/api/subscription/history`      | Required | -          | List billing history (paginated)             |
| GET    | `/api/subscription/plans`        | Required | -          | List active plans                            |
| GET    | `/api/subscription/cards`        | Required | -          | List registered cards                        |
| POST   | `/api/subscription/cards`        | Required | 3/min      | Register a card                              |
| DELETE | `/api/subscription/cards/{card}` | Required | -          | Delete a card                                |

---

## Error responses

### Validation errors (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email has already been taken."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

### Other errors (401 / 403 / 404 / 429)

```json
{
    "message": "Error description"
}
```

| Status code | Meaning                                                |
| ----------- | ------------------------------------------------------ |
| 401         | Unauthenticated (login required)                       |
| 403         | Forbidden (accessing another user's resource)          |
| 404         | Resource not found                                     |
| 422         | Validation error                                       |
| 429         | Rate limit exceeded                                    |

---

## Relationship to the Fincode API

```
Frontend (React)
    │
    ├── Card-number input → tokenized by Fincode.js (full PAN never reaches the server)
    │
    └── This app's REST API (documented here)
            │
            └── Fincode API (called only from the server)
                    ├── CustomerService     — Fincode customer management
                    ├── CardService         — card register / delete
                    ├── PlanService         — plan retrieval
                    └── SubscriptionService — subscription create / cancel
```

The frontend calls Fincode directly **only for card tokenization (Fincode.js)**.
Every other Fincode API call is performed server-side.

---

## Previewing with Swagger UI / Redocly

```bash
# Local preview with Redocly
npx @redocly/cli preview-docs docs/api/openapi.yml

# Syntax check
npx js-yaml docs/api/openapi.yml

# OpenAPI lint
npx @redocly/cli lint docs/api/openapi.yml
```
