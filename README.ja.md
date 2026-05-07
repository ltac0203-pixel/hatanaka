[English](./README.md) / 日本語

# hatanaka

**Fincode決済APIと統合したサブスクリプション管理Webアプリケーションのリファレンス実装**

Laravel 13 + React 19 + Inertia.js + TypeScript で構築された、Fincode決済を使ったサブスクリプション機能を実装するためのサンプルプロジェクトです。Fincodeを使った定期課金システムを構築する際の出発点としてご利用ください。

## 機能一覧

### 認証機能

- ユーザー登録・ログイン・ログアウト

### サブスクリプション管理

- プラン一覧の取得・詳細表示
- クレジットカードの登録・一覧・削除（Fincodeトークン化）
- サブスクリプションの契約・解約
- 決済履歴の確認

## 技術スタック

### Frontend

- **Framework**: React 19 + Inertia.js
- **Build Tool**: Vite
- **Language**: TypeScript
- **Styling**: Tailwind CSS

### Backend

- **Framework**: Laravel 13
- **Authentication**: Laravel Breeze + Sanctum
- **Database**: MySQL
- **Payment Gateway**: Fincode API

> セキュリティ問題を見つけた場合は公開 Issue を立てず、[SECURITY.ja.md](./SECURITY.ja.md) の手順に従ってください。

## 環境構築手順

### 前提条件

- PHP 8.3+
- Node.js v22+
- Composer
- Docker Desktop（推奨ルート）／または MySQL 8.0+・MariaDB 10.6+（マニュアルルート）
- Fincode アカウント（テストモードでOK）

Fincode のテストキーが無くても、登録／ログインまでの動作確認は可能です（決済画面に進むには `m_test_*` / `p_test_*` のキーが必要）。取得手順は [docs/getting-started/fincode-setup.ja.md](./docs/getting-started/fincode-setup.ja.md) を参照。

### クイックスタート（Docker、推奨）

DB と Mailpit をコンテナで起動するため、ローカルに MySQL を用意する必要がありません。

```bash
git clone https://github.com/ltac0203-pixel/hatanaka.git
cd hatanaka

composer setup            # 依存関係・.env・APP_KEY・フロントビルド
docker compose up -d      # MySQL（:3307）+ Mailpit（:8025）を起動
```

`.env` を以下のように編集（DB は Docker コンテナに合わせる、Fincode キーをセット）：

```ini
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=hatanaka
DB_USERNAME=hatanaka
DB_PASSWORD=hatanaka

FINCODE_API_KEY=m_test_...
FINCODE_PUBLIC_KEY=p_test_...
FINCODE_BASE_URL=https://api.test.fincode.jp

# Mailpit でメール確認したい場合（任意）
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

```bash
composer setup:db         # マイグレーション
composer dev              # Laravel + Vite + Queue を同時起動
```

| サービス | URL |
| --- | --- |
| アプリ | http://localhost:8000 |
| Mailpit（メール UI） | http://localhost:8025 |

### マニュアルセットアップ（ローカル MySQL）

Docker を使わない場合の手順です。詳細は [docs/getting-started/local-development.ja.md](./docs/getting-started/local-development.ja.md)。

1. MySQL に DB と USER を作成
    ```sql
    CREATE DATABASE subscription_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'app'@'localhost' IDENTIFIED BY 'change-me';
    GRANT ALL ON subscription_app.* TO 'app'@'localhost';
    ```
2. `composer setup`
3. `.env` を編集（DB 接続情報と Fincode キー）
4. `composer setup:db`
5. `composer dev` → http://localhost:8000

### pre-commit フックの有効化（推奨）

```bash
git config core.hooksPath .githooks

# macOS / Linux のみ
chmod +x .githooks/pre-commit scripts/check-secrets.sh
```

コミット前に `scripts/check-secrets.sh --staged` が実行され、`.env` などの機密ファイルや APIキーパターンの誤コミットを検出します。Windowsで `chmod` が使えない場合は 1 行目のみで有効化されます。

## コマンド一覧

```bash
# 開発
composer dev          # 開発サーバー起動
composer test         # PHPテスト実行
./vendor/bin/pint     # PHPリンター

# フロントエンド
npm run dev           # Vite開発サーバー
npm run build         # 本番ビルド
npm run lint          # ESLint
npm run test:run      # Vitestテスト実行

# データベース
php artisan migrate              # マイグレーション実行
php artisan migrate:fresh --seed # 初期化＋シード投入
```

## API エンドポイント

### Auth

| Method | Endpoint              | Description          |
| ------ | --------------------- | -------------------- |
| POST   | `/api/register`       | 新規ユーザー登録     |
| POST   | `/api/login`          | ログイン             |
| POST   | `/api/logout`         | ログアウト           |
| GET    | `/api/user`           | ログインユーザー情報 |
| GET    | `/api/session-status` | セッション有効性確認 |

### Subscription

| Method | Endpoint                           | Description            |
| ------ | ---------------------------------- | ---------------------- |
| GET    | `/api/subscription`                | 現在の契約状況取得     |
| POST   | `/api/subscription`                | サブスクリプション契約 |
| DELETE | `/api/subscription`                | サブスクリプション解約 |
| GET    | `/api/subscription/history`        | 決済履歴取得           |
| GET    | `/api/subscription/plans`          | プラン一覧取得         |
| GET    | `/api/subscription/cards`          | 登録カード一覧取得     |
| POST   | `/api/subscription/cards`          | 新規カード登録         |
| DELETE | `/api/subscription/cards/{cardId}` | カード削除             |

詳細なAPI仕様は [docs/api/openapi.yml](./docs/api/openapi.yml) を参照してください。

## ディレクトリ構造

```
hatanaka/
├── app/
│   ├── Http/Controllers/    # コントローラー
│   ├── Http/Resources/      # APIレスポンス整形
│   ├── Models/              # Eloquentモデル
│   ├── Policies/            # 認可ポリシー
│   └── Services/            # ビジネスロジック
│       └── Fincode/         # Fincode API クライアント・サービス
├── config/
│   └── fincode.php          # Fincode設定
├── database/
│   ├── migrations/          # DBマイグレーション
│   └── seeders/             # サンプルデータ
├── resources/
│   └── js/
│       ├── Pages/           # Inertia.jsページ
│       ├── Components/      # UIコンポーネント
│       └── types/           # TypeScript型定義
├── routes/
│   ├── web.php              # Webルート
│   └── api.php              # APIルート
└── tests/                   # テストケース
```

## ドキュメント

| トピック | ドキュメント |
| --- | --- |
| 環境構築 | [docs/getting-started/fincode-setup.ja.md](./docs/getting-started/fincode-setup.ja.md)、[local-development.ja.md](./docs/getting-started/local-development.ja.md)、[testing.ja.md](./docs/getting-started/testing.ja.md) |
| アーキテクチャ | [overview.ja.md](./docs/architecture/overview.ja.md)、[data-model.ja.md](./docs/architecture/data-model.ja.md)、[error-handling.ja.md](./docs/architecture/error-handling.ja.md)、[commit-guidelines.ja.md](./docs/architecture/commit-guidelines.ja.md) |
| API | [docs/api/README.ja.md](./docs/api/README.ja.md)（[openapi.yml](./docs/api/openapi.yml)） |
| 運用（任意） | [deployment.ja.md](./docs/operations/deployment.ja.md) — 自社環境でホスト運用を選んだ場合の参考。本プロジェクトは主にリファレンス実装としての利用を想定 |
| テンプレート流用 | [customization/index.ja.md](./docs/customization/index.ja.md)、[webhooks.ja.md](./docs/customization/webhooks.ja.md) |
| プロジェクトポリシー | [CONTRIBUTING.ja.md](./CONTRIBUTING.ja.md)、[SECURITY.ja.md](./SECURITY.ja.md) |

## コントリビューション

プルリクエスト・Issue を歓迎します。開発フローは [CONTRIBUTING.ja.md](./CONTRIBUTING.ja.md) を参照してください（[GitHub Flow](https://docs.github.com/ja/get-started/quickstart/github-flow) を採用、`main` から作業ブランチを切り `main` 向けに PR を送る）。コミット規約は [docs/architecture/commit-guidelines.ja.md](./docs/architecture/commit-guidelines.ja.md)。

## ライセンス

[Apache License 2.0](./LICENSE)

Copyright 2026 hatanaka contributors
