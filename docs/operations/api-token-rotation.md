# API token operations

How `/api/login` issued Sanctum personal access tokens are managed.

## Default behavior

- Calling `/api/login` with a `device_name` issues a Personal Access Token bound to that name
- `abilities` must be requested explicitly from the whitelist `subscription:read`, `subscription:write`, `card:read`, `card:write`. When omitted, the token defaults to the read-only set `['subscription:read', 'card:read']`
- Lifetime:
  - Read-only tokens (`*:read` only): **30 days**
  - Tokens that include any write ability (`*:write`): **7 days** (shortened to limit blast radius if leaked)

## Rotation (important)

Each successful `/api/login` **deletes any existing token bound to the same `device_name`**. This achieves:

- Prevents a leaked old token from staying live until its natural expiry
- Re-login is the canonical revocation path
- Users get a clean mental model: "log in again = forced rotation"

## Implications for long-running clients (mobile, monitors, bots)

Clients that stay alive across multiple days (mobile apps, monitoring daemons, bots, etc.) **must implement re-authentication logic** using one of the following:

1. **Proactive expiry checks**
   - Persist the `expires_at` from the login response and start re-login a few days before it
   - Write tokens live only 7 days; design a weekly re-auth UI or scheduled re-login job

2. **Reactive 401 handling**
   - On a 401 response, drop the token and trigger re-auth (either user input or stored credentials)
   - If re-auth fails, surface a screen asking the user to act

3. **Beware concurrent logins from the same `device_name`**
   - If two clients share a `device_name`, the second login wipes the first one's token
   - Use device-unique identifiers (e.g. `ios-iphone15-{uuid}`, `monitor-prod-1`)

4. **Request least-privilege abilities**
   - Monitoring or display-only clients should request only `abilities=["subscription:read","card:read"]`
   - Request `*:write` only when the operation truly needs it (cancel, add card) to manage the trade-off between shorter lifetime and reduced blast radius

## CSRF / auth path

`/api/login` is protected by **`throttle:api-login`**:

- 5 requests/min keyed on email + IP
- 20 requests/min keyed on IP

This is the first line of defense against brute force and dictionary attacks. If a client falls into a re-auth loop it will immediately hit the limiter, so implement exponential backoff.

## Carrying an old token forward

There is no supported scenario for "keep using the old token without rotating". For continuity, choose one of:

- Trigger an intentional user-driven re-login to swap tokens
- Issue a second token under a different `device_name` (the old one survives until its natural expiry)
