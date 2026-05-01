[English](./local-development.md) / 日本語

# ローカル開発環境構築

ローカルでアプリを動かすまでの一連の手順です。README は要約のみ、本ドキュメントが完全版です。

## 前提条件

| ツール | バージョン | 備考 |
| --- | --- | --- |
| PHP | 8.3+ | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | 22+ | `node -v`。**[Volta](https://volta.sh/) 推奨**。`package.json` の `volta` フィールドで本リポジトリは Node 22.11.0 に自動ピンされる。Volta が無い場合は [nvm](https://github.com/nvm-sh/nvm) でも可。 |
| MySQL | 8.0+ または MariaDB 10.6+ | テストは MariaDB を想定。開発はどちらでも可 |
| Fincode アカウント | テストモード | [fincode-setup.ja.md](./fincode-setup.ja.md) 参照 |

PHP 拡張は Laravel 12 既定：`mbstring`・`pdo_mysql`・`bcmath`・`intl`・`openssl`・`tokenizer`・`xml`・`ctype`・`json`・`fileinfo`・`dom`・`curl`。

## 0. 推奨ツールチェーン

OS 共通で「**Volta + Mailpit + pre-commit フック有効化**」を推奨します。Windows ではさらに **Git Bash**（Git for Windows 同梱）でシェル作業を行うのが安定します。

| ツール | 用途 | Windows 入手 | macOS 入手 | Linux 入手 |
| --- | --- | --- | --- | --- |
| Git for Windows / Git Bash | `composer dev` の実行シェル | <https://git-scm.com/download/win> | 標準同梱 | 標準同梱 |
| [Volta](https://volta.sh/) | Node.js 22 の自動ピン | `winget install Volta.Volta` | `curl https://get.volta.sh \| bash` | `curl https://get.volta.sh \| bash` |
| [Mailpit](https://mailpit.axllent.org/) | 開発時メール UI | `winget install axllent.mailpit` または `scoop install mailpit` | `brew install mailpit` | 公式インストーラ（後述） |

Volta 導入後、初回のみ次を実行しておけば、本リポジトリに `cd` した瞬間に Node 22.11.0 が選択されます（`package.json` の `volta` フィールドが効きます）。

```bash
volta install node@22.11.0
```

すべて OSS / 無償ツールです。

## 1. データベース作成

```sql
CREATE DATABASE subscription_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL ON subscription_app.* TO 'app'@'localhost';
```

テスト用も作成：

```sql
CREATE DATABASE subscription_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON subscription_app_test.* TO 'app'@'localhost';
```

## 2. `composer setup` 実行

```bash
composer setup
```

`composer.json` のスクリプトは以下 4 ステップ：

1. `composer install` — PHP 依存関係インストール。
2. `.env` がなければ `.env.example` をコピー。
3. `php artisan key:generate` — `APP_KEY` 生成。
4. `npm install && npm run build` — JS 依存関係インストールと本番ビルド（Vite dev サーバーを起動していなくてもアプリが動くようにするため）。

> マイグレーションは**含まれません**。次の手順で `.env` の DB 認証情報を設定してから `composer setup:db`（手順 4）で実行します。

途中失敗した場合は原因を直してから個別コマンドを再実行。`composer setup` 全体の再実行も安全ですが時間がかかります。

## 3. 環境変数の設定

`.env` を編集：

```ini
APP_NAME="Subscription App"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_app
DB_USERNAME=app
DB_PASSWORD=change-me

FINCODE_API_KEY=m_test_xxxxxxxxxxxxxxxxxxxxxxx
FINCODE_PUBLIC_KEY=p_test_xxxxxxxxxxxxxxxxxxxxxxx
FINCODE_BASE_URL=https://api.test.fincode.jp

MAIL_MAILER=log    # storage/logs/laravel.log にメール出力。Mailpit があれば smtp に切替可
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Fincode キーの取得は [fincode-setup.ja.md](./fincode-setup.ja.md) 参照。

## 4. マイグレーション実行

```bash
composer setup:db
```

中身は `php artisan migrate --force`。`git pull` 後に新しいマイグレーションを適用したいときも同じコマンドで再実行できます。

## 5. アプリ起動

```bash
composer dev
```

`npx concurrently` で 4 プロセスを並走：

| プロセス | 役割 | 既定ポート |
| --- | --- | --- |
| `php artisan serve` | Laravel HTTP サーバー | `:8000` |
| `php artisan queue:listen --tries=1` | キューワーカー（イベント・メール） | — |
| `php artisan pail --timeout=0` | `storage/logs/laravel.log` の tail | — |
| `npm run dev` | Vite dev サーバー（HMR） | `:5173` |

ブラウザで <http://localhost:8000> を開く。Inertia が React アプリを読み込み、Vite がフロントの HMR を担当。

`Ctrl+C` で 4 プロセスとも停止（`--kill-others` 設定済み）。

## 6. サンプルデータ投入（任意）

```bash
php artisan migrate:fresh --seed
```

DB を破棄してからシードを再実行。スキーマ／シーダー反復時に有効。**実ユーザーデータが入っている DB に対しては絶対に実行しない**こと。

## 7. 開発時のメール送信

`.env.example` の既定は `MAIL_MAILER=log`。送信メール（登録・メール認証）は `storage/logs/laravel.log` に追記され、`composer dev` の `pail` ペインから確認できる。

UI で確認したい場合は **Mailpit** を導入する（推奨）。

```bash
# macOS
brew install mailpit

# Windows
winget install axllent.mailpit
# または: scoop install mailpit

# Linux（公式インストーラ）
sudo bash < <(curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh)
```

別ターミナルで `mailpit` を起動すると `:1025 (SMTP)` / `:8025 (UI)` で待ち受ける。`.env` を以下に変更：

```ini
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

ブラウザで <http://localhost:8025> を開けば、登録・メール認証などの送信メールがリアルタイムに確認できる。

## 8. tinker による動作確認

`php artisan tinker` がシステムを試す最速手段：

```php
// ユーザー用の Fincode カスタマー作成
$user = App\Models\User::factory()->create();
$svc = app(App\Services\CustomerSyncService::class);
$svc->ensureFincodeCustomer($user);

// Circuit Breaker の状態確認
$cb = app(App\Services\Fincode\CircuitBreaker::class);
$cb->getState();          // 'closed' | 'open' | 'half-open'
$cb->getFailureCount();   // 現在の失敗カウント

// ブレーカリセット
$cb->reset();
```

## 9. IDE 推奨設定

- **PhpStorm**: Laravel + Pint + PHP CS Fixer プラグイン有効化。フォーマッタは Pint に統一（`composer test` と整合）。
- **VS Code**: **Laravel Extension Pack**・**ESLint** 拡張を導入。ESLint はリポジトリルートの `eslint.config.js` を使用。（本リポジトリは Prettier を導入していません）

## 10. pre-commit フック（**初回セットアップ時に必須**）

`composer setup` 完了後、すぐに以下を実行してください。`.env` や Fincode キー (`m_test_*` / `p_test_*` 等) の誤コミットを防ぐ最後の砦になります。

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit scripts/check-secrets.sh   # macOS / Linux のみ
```

Windows では `chmod` 行は不要で、1 行目のみで有効化されます。フックは `scripts/check-secrets.sh --staged` を実行し、`.env`・`credentials.json`・`AKIA*` / `sk_live_*` / `ghp_*` などのパターンを含むコミットを `exit 1` でブロックします。

## Windows 固有の注意点

- 推奨シェルは **Git Bash**（Git for Windows 同梱）。`composer dev` を起動する際は Git Bash か WSL を使ってください。PowerShell でも動作はしますが、`npx concurrently` の色出力やシグナル処理が乱れる場合があります。
- pre-commit フックの `chmod` は不要。`git config core.hooksPath .githooks` のみで有効化されます。
- `.env` 内のパスはスラッシュ区切り（バックスラッシュ不可）。

## よくあるトラブル

| 症状 | 原因の見当 |
| --- | --- |
| 初回マイグレーションで `SQLSTATE[HY000] [1045]` | `.env` の DB 認証情報が DB 側と不一致 |
| フォーム送信時に 419 Page Expired | セッション不在。`SESSION_DRIVER` を確認。`database` の場合は `php artisan session:table && php artisan migrate` を済ませる |
| Vite ビルドで `Failed to resolve import` | pull 後に `npm install` 未実行 |
| Fincode 呼び出しが `unauthorized` | キーのプレフィックス不一致（`m_prod_*` を `m_test_*` のところに使っている等）または `FINCODE_BASE_URL` 不整合 |
| キューイベントが発火していないように見える | `composer dev` 未起動か `QUEUE_CONNECTION=sync`（同期実行で例外が握り潰されることがある） |

## 次に読むもの

- [testing.ja.md](./testing.ja.md) — テスト環境とテスト DB。
- [fincode-setup.ja.md](./fincode-setup.ja.md) — Fincode アカウントとテストキー。
- [../architecture/overview.ja.md](../architecture/overview.ja.md) — レイヤ全体の繋がり。
