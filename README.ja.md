[English](./README.md) / 日本語

# hatanaka

**Fincode決済APIと統合したサブスクリプション管理Webアプリケーションのリファレンス実装**

Laravel 12 + React 19 + Inertia.js + TypeScript で構築された、Fincode決済を使ったサブスクリプション機能を実装するためのサンプルプロジェクトです。Fincodeを使った定期課金システムを構築する際の出発点としてご利用ください。

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

- **Framework**: Laravel 12
- **Authentication**: Laravel Breeze + Sanctum
- **Database**: MySQL
- **Payment Gateway**: Fincode API

> セキュリティ問題を見つけた場合は公開 Issue を立てず、[SECURITY.ja.md](./SECURITY.ja.md) の手順に従ってください。

## 環境構築手順

### 前提条件

- PHP 8.3+
- Node.js v22+
- Composer
- MySQL 8.0+ または MariaDB
- Fincodeアカウント（テストモードでOK）

> **推奨ツールチェーン**: Git Bash（Windows）+ Volta + Mailpit + pre-commit フック有効化。すべて無償OSS。詳細は [docs/getting-started/local-development.ja.md](./docs/getting-started/local-development.ja.md#0-推奨ツールチェーン) を参照。

### 1. プロジェクトのセットアップ

```bash
# リポジトリをクローン
git clone https://github.com/ltac0203-pixel/hatanaka.git
cd hatanaka

# 依存関係インストール、.env作成、APP_KEY生成、フロントビルド（マイグレーションは含まない）
composer setup
```

### 2. 環境変数の設定

`.env` ファイルを編集し、以下の設定を行います。

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

`FINCODE_API_KEY` および `FINCODE_PUBLIC_KEY` はFincodeの管理画面から取得してください。テスト用は `m_test_` / `p_test_` で始まるキーを使用します。`FINCODE_BASE_URL` は本番環境では `https://api.fincode.jp`、テスト環境では `https://api.test.fincode.jp` を指定します。

Fincodeアカウントの取得・テスト環境のセットアップ手順は [docs/getting-started/fincode-setup.ja.md](./docs/getting-started/fincode-setup.ja.md) を参照してください。

### 3. マイグレーションの実行

データベースを作成し、`.env` の設定が完了したらマイグレーションを実行します。

```bash
composer setup:db
```

`composer setup` から分離しているのは、マイグレーションには有効な DB 認証情報が必要で、それを 2 で設定するためです。

#### pre-commit フックの有効化（推奨）

```bash
git config core.hooksPath .githooks

# macOS / Linux
chmod +x .githooks/pre-commit scripts/check-secrets.sh
```

コミット前に `scripts/check-secrets.sh --staged` が実行され、`.env` などの機密ファイルや APIキーパターンの誤コミットを検出します。
Windowsで `chmod` が使えない場合は `git config core.hooksPath .githooks` のみ設定してください。

### 4. アプリケーションの起動

```bash
# 開発サーバー起動（Laravel + Vite + Queue を同時起動）
composer dev
```

ブラウザで `http://localhost:8000` にアクセスしてください。

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
