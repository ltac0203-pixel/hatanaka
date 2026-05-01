# docs/api — API仕様書

English version: [README.md](./README.md)

## ファイル一覧

| ファイル                     | 説明                                                                |
| ---------------------------- | ------------------------------------------------------------------- |
| [openapi.yml](./openapi.yml) | **本アプリ REST API** の OpenAPI 3.0.3 仕様書（全13エンドポイント） |

> **重要:** `openapi.yml` は本アプリが外部に公開するAPIの仕様です。
> Fincode APIはサーバーサイドのみが直接呼び出します（フロントエンドからは呼び出しません）。

---

## 認証方式

本APIは [Laravel Sanctum](https://laravel.com/docs/sanctum) による2種類の認証をサポートします。

### セッション認証（推奨: Webブラウザ）

`POST /login` 後にセッションクッキーが発行されます。
ブラウザからのリクエストには自動送信されます。

```http
POST /api/login
Content-Type: application/json

{ "email": "user@example.com", "password": "password123" }
```

> CSRF保護のため `X-XSRF-TOKEN` ヘッダーが必要です。

### Bearerトークン認証（モバイル・外部クライアント）

`POST /login` に `device_name` を含めるとトークンが返されます。

```http
POST /api/login
Content-Type: application/json

{ "email": "user@example.com", "password": "password123", "device_name": "MyApp-iPhone" }
```

レスポンス:

```json
{
  "user": { ... },
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

以降のリクエストにトークンを付与:

```http
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## エンドポイント早見表

| メソッド | パス                             | 認証 | レート制限 | 説明                               |
| -------- | -------------------------------- | ---- | ---------- | ---------------------------------- |
| POST     | `/api/register`                  | 不要 | 5回/分     | ユーザー登録                       |
| POST     | `/api/login`                     | 不要 | 5回/分     | ログイン                           |
| GET      | `/api/session-status`            | 不要 | -          | 認証状態確認                       |
| POST     | `/api/logout`                    | 必須 | -          | ログアウト                         |
| GET      | `/api/user`                      | 必須 | -          | 認証ユーザー情報取得               |
| GET      | `/api/subscription`              | 必須 | -          | アクティブなサブスクリプション取得 |
| POST     | `/api/subscription`              | 必須 | 3回/分     | サブスクリプション登録             |
| DELETE   | `/api/subscription`              | 必須 | -          | サブスクリプション解約             |
| GET      | `/api/subscription/history`      | 必須 | -          | 決済履歴取得（ページネーション）   |
| GET      | `/api/subscription/plans`        | 必須 | -          | アクティブプラン一覧取得           |
| GET      | `/api/subscription/cards`        | 必須 | -          | 登録済みカード一覧取得             |
| POST     | `/api/subscription/cards`        | 必須 | 3回/分     | カード登録                         |
| DELETE   | `/api/subscription/cards/{card}` | 必須 | -          | カード削除                         |

---

## エラーレスポンス形式

### バリデーションエラー (422)

```json
{
    "message": "入力内容を確認してください。",
    "errors": {
        "email": ["このメールアドレスは既に使用されています。"],
        "password": ["パスワードは8文字以上で入力してください。"]
    }
}
```

### その他のエラー (401 / 403 / 404 / 429)

```json
{
    "message": "エラーの説明"
}
```

| ステータスコード | 意味                                         |
| ---------------- | -------------------------------------------- |
| 401              | 未認証（ログインが必要）                     |
| 403              | 権限なし（他ユーザーのリソースへのアクセス） |
| 404              | リソースが見つからない                       |
| 422              | バリデーションエラー                         |
| 429              | レート制限超過                               |

---

## Fincode APIとの関係

```
フロントエンド (React)
    │
    ├── カード番号入力 → Fincode.js でトークン化（フルナンバーはサーバー非到達）
    │
    └── 本アプリ REST API（このドキュメント）
            │
            └── Fincode API（サーバーサイドのみ直接呼び出し）
                    ├── CustomerService  — Fincodeカスタマー管理
                    ├── CardService      — カード登録・削除
                    ├── PlanService      — プラン取得
                    └── SubscriptionService — サブスクリプション作成・解約
```

フロントエンドが直接Fincode APIを呼び出すのは **カードトークン発行（Fincode.js）のみ** です。
それ以外の全Fincode API操作はサーバーサイドで行われます。

---

## Swagger UIでの確認

```bash
# Redoclyを使ったローカルプレビュー
npx @redocly/cli preview-docs docs/api/openapi.yml

# 構文チェック
npx js-yaml docs/api/openapi.yml

# OpenAPI仕様検証
npx @redocly/cli lint docs/api/openapi.yml
```
