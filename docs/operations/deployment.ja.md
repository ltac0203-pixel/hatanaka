[English](./deployment.md) / 日本語

# 本番デプロイ

> **このドキュメントは任意の参考資料です。** 本リポジトリは Fincode 連携のリファレンス実装として配布されており、想定される利用フローは「クローン → ローカルで動作確認 → 自社サービスへ取り入れ」です。本ドキュメントは、評価後にこのコードをそのまま自社環境にホスト運用する選択肢を取った場合の参考としてご利用ください。

本番運用の手順です。Linux 上の典型的な構成（Nginx + PHP-FPM + MySQL + Supervisor）を想定。マネージド環境では適宜読み替えてください。

## 事前チェックリスト

| 項目 | 必須 | 補足 |
| --- | --- | --- |
| `APP_ENV=production` | ✅ | デバッグページとノイジーなログを抑制 |
| `APP_DEBUG=false` | ✅ | スタックトレース漏洩防止 |
| `APP_KEY` | ✅ | `php artisan key:generate` で 1 度だけ生成し、安全に保管 |
| `APP_URL` | ✅ | 実ホスト名と一致させる（絶対 URL・CSRF に影響） |
| `FINCODE_API_KEY=m_prod_...` | ✅ | 本番シークレット。`m_test_*` ではない |
| `FINCODE_PUBLIC_KEY=p_prod_...` | ✅ | 本番公開鍵（ブラウザでのトークン化用） |
| `FINCODE_BASE_URL=https://api.fincode.jp` | ✅ | 本番 API エンドポイント |
| `SESSION_DRIVER=database`（または `redis`） | ✅ | 複数インスタンス構成では `array` / `file` 不可 |
| `CACHE_STORE=database`（または `redis`） | ✅ | Circuit Breaker はキャッシュ参照。レプリカ間で共有されないとブレーカが破綻 |
| `QUEUE_CONNECTION=database`（または `redis`） | ✅ | ワーカー常駐が必須（後述） |
| `MAIL_MAILER` | ✅ | 実ドライバを設定（`smtp`・`ses`・`postmark` 等）。`log` 不可 |
| `LOG_CHANNEL=stack` | 推奨 | 既定 `stack` は `storage/logs/laravel.log` と stderr に出力 |
| `TRUSTED_PROXIES` | LB 経由なら必須 | 設定しないと CSRF やレート制限が LB の IP で集計される |
| HTTPS | ✅ | Cookie が Secure。混在コンテンツで Inertia が壊れる |

> **起動時に検証される**：`app/Services/Fincode/FincodeApiConfigValidator.php`・`FincodeConfigValidator.php` が Fincode 設定の欠落・不整合を検知して例外を投げる。これらをバイパスしないこと。

## ビルド

```bash
# 1. ソース取得
git fetch --all
git checkout <release-tag-or-sha>

# 2. PHP 依存（dev 除外）
composer install --no-dev --optimize-autoloader --no-interaction

# 3. JS 依存・ビルド
npm ci
npm run build

# 4. マイグレーション
php artisan migrate --force

# 5. フレームワークのキャッシュ
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

本番では `composer dev` は使わない。各 cache は柔軟性と引き換えに起動を高速化するため、新ビルドのデプロイ時にはクリアし直す。

## Web サーバー

最小限の Nginx 設定：

```nginx
server {
    listen 443 ssl http2;
    server_name your.domain.example;

    root /var/www/subscription-app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # ビルド済みアセットは長期キャッシュ
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

HTTP → HTTPS リダイレクトはロードバランサ側か、別の `listen 80` ブロックで。

## キューワーカー（必須）

イベント（`SubscriptionCreated`・`CardRegistered`・リスナー経由の監査ログ書き込み・メール）はキュー経由で処理されます。**Supervisor 配下で `queue:work` を常駐**させてください。`composer dev` の `queue:listen` はローカル専用です。

`/etc/supervisor/conf.d/subscription-app-worker.conf`：

```ini
[program:subscription-app-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/subscription-app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/subscription-app-worker.log
stopwaitsecs=3600
```

デプロイ後はワーカーに新コードを読み込ませる：

```bash
php artisan queue:restart
```

`--max-time=3600` で 1 時間ごとに自然終了 → Supervisor が再起動。デプロイなしでもメモリリーク蓄積を断つ仕組み。

## スケジューラ（将来拡張）

本テンプレートは現状スケジュールタスクを持ちませんが、fork で追加（例：Fincode との日次照合）する場合は Laravel Scheduler を組み込む：

```cron
* * * * * cd /var/www/subscription-app && php artisan schedule:run >> /dev/null 2>&1
```

## ログとローテーション

`storage/logs/laravel.log` は既定で無制限に肥大化。logrotate を設定：

```
/var/www/subscription-app/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    copytruncate
}
```

複数ホスト構成では中央ログ基盤に集約（CloudWatch・Datadog・Loki など）。`LOG_CHANNEL=stack` または syslog チャンネル経由で連携可能。

## セキュリティヘッダー

`app/Http/Middleware/SecurityHeaders.php` が CSP・HSTS・X-Frame-Options 等を付与。初回デプロイ時は **CSP の report-only モード**が便利：`POST /api/security/csp-reports` で違反を数日収集 → ポリシー調整 → 強制モード。

出力するヘッダーは `config/security.php` で制御。本番投入前に一度レビューしてください。

## Fincode API キーのローテーション

Fincode コンソールでキーをローテートする際：

1. 新しいペアを発行（`m_prod_*`・`p_prod_*`）。
2. シークレットストアに新キーを配置。
3. **古いキーをまだ無効化しないまま**新環境変数をデプロイ。
4. 新リクエスト成功を構造化 Fincode ログで確認。
5. Fincode コンソールで旧キーを失効。

Circuit Breaker 状態はキャッシュ管理 → キー交換時にフラッシュ不要。

## ロールバック

マイグレーションは常に動く `down()` を持つことをチーム規約にしてください（マージ前に確認）。1 デプロイ分の戻し方：

```bash
git checkout <previous-tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate:rollback --step=N   # 不良リリースがマイグレーションを足していた場合のみ
php artisan config:cache route:cache view:cache event:cache
php artisan queue:restart
```

安全に戻せないマイグレーション（破壊的変更等）の場合は **forward-fix**（必要な状態を復元する新リリースを出す）が安全。

## バックアップ

本テンプレートはバックアップ機構を提供しません（インフラ責務）。最低限：MySQL を日次論理バックアップ、四半期ごとに復元検証。`audit_logs` は再現不可能な証跡なので必ずバックアップ対象に含める。

## 可観測性

標準で備わるもの：

- 構造化アプリログ（`storage/logs/laravel.log`）
- 監査ログテーブル（`audit_logs`）
- キャッシュ上の Circuit Breaker 状態（tinker から `app(CircuitBreaker::class)->getState()` で確認）

メトリクスエンドポイントや APM フックは標準では含みません。必要なら fork で Laravel Pulse・OpenTelemetry・ベンダー SDK を統合してください。

## 次に読むもの

- [../getting-started/local-development.ja.md](../getting-started/local-development.ja.md) — ローカルとの差分。
- [../architecture/error-handling.ja.md](../architecture/error-handling.ja.md) — 監視で何を検知すべきか。
- [../customization/index.ja.md](../customization/index.ja.md) — 本番投入前に変更しておくべき箇所。
