# Fincode setup

Japanese version: [fincode-setup.ja.md](./fincode-setup.ja.md)

To run this application you need a [Fincode](https://www.fincode.jp/) account and API keys. The test mode is free to use.

> **Note:** Fincode is a Japanese payment platform. Its sign-up flow, dashboard, and developer documentation are written in Japanese. If you only need to evaluate this reference implementation, the test-mode steps below are enough.

## 1. Create a Fincode account

1. Sign up (create a tenant) on the [Fincode website](https://www.fincode.jp/).
2. Verify your email and log in to the management console at [https://management.test.fincode.jp/](https://management.test.fincode.jp/).

## 2. Obtain API keys

From the `APIキー` (API Keys) menu in the management console, collect the following values.

| Environment variable      | Source                                                                  | Purpose                                                          |
| ------------------------- | ----------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `FINCODE_API_KEY`         | API Keys → secret key (`m_test_...`)                                    | Server-side API calls                                            |
| `FINCODE_PUBLIC_KEY`      | API Keys → public key (`p_test_...`)                                    | In-browser card tokenization                                     |
| `FINCODE_BASE_URL`        | Test: `https://api.test.fincode.jp` / Production: `https://api.fincode.jp` | API endpoint                                                  |
| `FINCODE_TENANT_SHOP_ID`  | Platform / multi-tenant accounts only                                   | Sent as the `Tenant-Shop-Id` HTTP header. Leave empty for single-shop accounts. |

Set them in your `.env`.

> `FINCODE_TENANT_SHOP_ID` is **only required if your Fincode account is a platform-mode (multi-tenant) account**. For standard single-shop accounts, leave it unset and the header will be omitted. The CI pipeline sets a dummy value so the test suite can boot without complaining.

## 3. Create a test plan

This application stores plan information on the Fincode side and fetches it via API at subscription time.

1. In the Fincode console, go to `定期課金` (Recurring billing) → `プラン` (Plans) → `新規作成` (Create).
2. Configure the plan name, amount, and billing interval (monthly, yearly, etc.).
3. Note the resulting plan ID (format: `plan_xxxxxx`).

Create several plans so the in-app plan-selection screen has options to display.

## 4. Test card numbers

In Fincode test mode you can use the following test card numbers.

| Card number          | Brand      |
| -------------------- | ---------- |
| `4111111111111111`   | Visa       |
| `5555555555554444`   | MasterCard |
| `3530111333300000`   | JCB        |

- Any future expiry date and any 3–4 digit CVC will work.
- See the [Fincode official documentation](https://docs.fincode.jp/) for details.

## 5. Webhooks (optional)

If you want to receive recurring-billing results asynchronously, configure a webhook endpoint in the Fincode console. This reference implementation does not ship with a webhook handler — add one if you need it.

## References

- [Fincode official site](https://www.fincode.jp/)
- [Fincode developer docs](https://docs.fincode.jp/)
- [Fincode API reference](https://docs.fincode.jp/api)
