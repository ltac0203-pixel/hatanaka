[English](./testing.md) / 日本語

# テストガイド

テストの実行方法、配置、実 Fincode に依存させない書き方をまとめます。

## テストスタック

| レイヤ | ツール | 場所 |
| --- | --- | --- |
| PHP | PHPUnit 11 | `tests/Feature/`、`tests/Unit/` |
| JS / React | Vitest | `resources/js/**/*.test.ts(x)` |
| HTTP fake | `Illuminate\Support\Facades\Http::fake()` | テストごと |

`phpunit.xml` は `Unit` と `Feature` の 2 スイートを定義。実行：

```bash
composer test           # config:clear → artisan test（両スイート）
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

## テスト DB

`phpunit.xml` は `DB_CONNECTION=mysql` と charset/collation を設定しますが、**`DB_DATABASE` は設定しません**。`.env.testing` に置いてください：

```ini
# .env.testing
APP_ENV=testing
DB_CONNECTION=mysql
DB_DATABASE=subscription_app_test
DB_USERNAME=app
DB_PASSWORD=change-me
```

DB 自体は事前に作成（[local-development.ja.md](./local-development.ja.md) の「データベース作成」）。マイグレーションは `RefreshDatabase` / `DatabaseMigrations` トレイトがテスト単位で走らせるため手動適用は不要。

> テストは MariaDB / MySQL 想定。`subscriptions.active_user_id` の生成カラムが MySQL 系の構文に依存しており、SQLite では動かない。

## ディレクトリ構成

| パス | 配置するもの |
| --- | --- |
| `tests/Unit/` | 純粋な単体テスト。DB・HTTP・コンテナ副作用なし。サブディレクトリ：`Config`・`Enums`・`Exceptions`・`Jobs`・`Listeners`・`Models`・`Providers`・`Services` |
| `tests/Feature/` | Laravel コンテナを起動して HTTP レベルでテスト。サブディレクトリ：`Api`（REST エンドポイント）・`Auth`（ログイン／登録／メール認証）・`Database`（マイグレーション／スキーマ不変条件）・`Requests`（FormRequest 検証）・`Web`（Inertia ルート）。直下のファイルは横断テスト：`ErrorPageTest`・`EventDiscoveryTest`・`ExceptionHandlerTest`・`PolicyTest`・`ProfileTest`・`SecurityTest` |

判断基準：`$this->postJson(...)` を呼ぶか、ルートを起動するなら Feature テスト。

## 部分実行

```bash
# 単一クラス
php artisan test --filter=SubscriptionStoreTest

# 単一メソッド
php artisan test --filter='SubscriptionStoreTest::test_user_with_active_subscription_gets_409'

# 最初の失敗で停止
php artisan test --stop-on-failure

# 直前の失敗のみ再実行
php artisan test --filter=... --rerun
```

## `composer test` の挙動

```jsonc
"test": [
    "@php artisan config:clear --ansi",
    "@php artisan test"
]
```

`config:clear` は重要。前回実行のキャッシュを除去しないと `phpunit.xml` の `<env>` 上書きが効かず「ローカルでは通るが CI で落ちる」が起きやすい。

## フロントエンドのテスト

```bash
npm run test        # 対話 watch
npm run test:run    # 1 回限り（CI 用）
```

Vitest の設定は `vite.config.js`。テストはコンポーネントと同居（`Foo.tsx` ↔ `Foo.test.tsx`）。

## 実 Fincode API は**叩かない**

Fincode テストモードは無料ですが、テストはこれに依存しないでください。理由：

1. **再現性**：テストモードは共有インフラ。レイテンシとレート制限が予測不能。
2. **Idempotency-Key の挙動**：再実行で同じキーを使うと Fincode 側がキャッシュ応答を返し、実際の失敗を覆い隠す。
3. **CI に Fincode 認証情報はない**。これに依存するテストは skip か fail し、どちらも品質シグナルとしてノイズになる。

以下のいずれかでモック：

### A. `Http::fake()` — `FincodeClient` 経由の呼び出しに推奨

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.test.fincode.jp/v1/customers' => Http::response([
        'id' => 'c_test_dummy',
        'name' => 'Test User',
    ], 200),
]);

$service = app(App\Services\Fincode\CustomerService::class);
$customer = $service->create($user);

Http::assertSent(fn ($req) => $req->url() === 'https://api.test.fincode.jp/v1/customers');
```

### B. Fincode サービスをモック — HTTP 詳細を見たくない場合

```php
$this->mock(App\Services\Fincode\CardService::class)
    ->shouldReceive('createCard')
    ->once()
    ->andReturn(new App\Services\Fincode\CardDto(...));
```

`CardManager` や `SubscriptionManager` の調整ロジックだけテストしたい時に有効。

### C. Circuit Breaker のテストダブル

`CircuitBreaker` はキャッシュを参照する。`phpunit.xml` で `CACHE_STORE=array` のため各テストは clean breaker から始まる。状態を強制するには：

```php
Cache::store()->put('fincode_circuit_breaker:state', 'open', 300);
Cache::store()->put('fincode_circuit_breaker:opened_at', time(), 300);
```

その上で `CircuitBreakerOpenException` が投げられることを assert。

## カバレッジ

```bash
php artisan test --coverage                 # テキストサマリ
php artisan test --coverage-html=coverage   # HTML レポート
```

CI は **statement カバレッジ 50% 閾値**を強制しています（`.github/workflows/ci.yml` 参照）。50% を下回る PR は `Check coverage threshold` ステップで失敗します。ローカルで CI と同じ指標を確認するには `--coverage-clover=coverage.xml` を使用してください。

## このプロジェクトでの良いテストの書き方

- **公開境界をテストする**。`tests/Feature/Api/*` は `routes/api.php` を駆動。Manager メソッドを直接呼ばない。
- **シーダーではなくファクトリ**。シーダーは人間向けデータ。ファクトリは意図を明示する隔離フィクスチャ。
- **テスト間で状態をリセット**。`RefreshDatabase` を使い、テスト間のコミット保持に依存しない。
- **監査ログをアサート**。状態変更は `audit_logs` 行を生むはず。それを確認する：

```php
$this->assertDatabaseHas('audit_logs', [
    'event' => 'subscription.created',
    'auditable_type' => Subscription::class,
    'user_id' => $user->id,
]);
```

- **ログ文字列を assert しない**。文言は変わる。挙動を assert する。

## 次に読むもの

- [local-development.ja.md](./local-development.ja.md) — DB セットアップ。
- [../architecture/error-handling.ja.md](../architecture/error-handling.ja.md) — Feature テストが依存する例外 → HTTP マッピング。
- [../architecture/data-model.ja.md](../architecture/data-model.ja.md) — 成功操作後の DB 状態。
