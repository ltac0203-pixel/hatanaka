[English](./webhooks.md) / 日本語

# Fincode Webhook 統合

> **本テンプレートには Webhook ハンドラは同梱されていません**。追加する場合の設計指針をまとめます。

## なぜ Webhook が必要か

同期フロー（契約 → Fincode が契約作成 → ユーザーに成功表示）は **契約成立のハッピーパス**だけをカバーします。それ以降の定期課金は非同期：Fincode がスケジュールでカード課金を行い、結果を通知します。Webhook がないと、`subscription_results` テーブルはそれらの課金を知ることができず、Fincode のダッシュボード上でしか見えません。

以下のいずれかが必要なら Webhook を実装してください：

- ユーザーの請求履歴に「直近の決済結果」を正確に表示。
- 失敗課金への反応（Dunning、ステータスを `unpaid` に遷移）。
- 課金成功時の下流プロビジョニング（権限・フィーチャーフラグ等）。

## エンドポイント設計

コントローラとルートを追加します。推奨配置：

```
app/Http/Controllers/Api/Webhooks/FincodeWebhookController.php
routes/api.php   →  POST /api/webhooks/fincode
```

ルートは **`auth:sanctum` グループの外**に置くこと（Webhook はユーザーではなく Fincode から到着するため）。

```php
// routes/api.php
Route::post('/webhooks/fincode', App\Http\Controllers\Api\Webhooks\FincodeWebhookController::class)
    ->middleware(['throttle:120,1'])  // 多めに（Fincode はリトライする）
    ->name('api.webhooks.fincode');
```

ルートキャッシュ（`php artisan route:cache`）を使う場合、クロージャはキャッシュ対象外なので、必ずクラスとしてコントローラを定義してください。

## 署名検証（必須）

**署名検証なしの Webhook エンドポイントは、課金状態を変更する未認証 POST エンドポイント**です。URL を知っている人なら誰でも課金結果を偽装できます。

Fincode は署名ヘッダを送ります（正確なヘッダ名と HMAC アルゴリズムは Fincode の現行ドキュメントを参照。API バージョンで変わる可能性があります）。何をするにも先に検証してください：

```php
public function __invoke(Request $request): Response
{
    $signature = $request->header('Fincode-Signature');
    $payload = $request->getContent();
    $secret = config('fincode.webhook_secret');

    $expected = hash_hmac('sha256', $payload, $secret);

    if (! hash_equals($expected, $signature ?? '')) {
        abort(401);
    }

    // … イベント処理
}
```

`FINCODE_WEBHOOK_SECRET` を `.env` に保存し、`config/fincode.php` で読み込みます。ローテーションは Fincode 側 → 自分の環境変数の順で。

## 冪等性（必須）

Webhook は **at-least-once**。Fincode は非 2xx 応答・タイムアウト、さらにそのどちらでもない理由でも再送します。ハンドラは 2 回目以降の配信でも 1 回目と同じ結果を返さねばなりません。

確実な手法 2 つ：

### A. Fincode のイベント ID で重複排除

通常、Webhook システムはユニークなイベント ID を含みます。それを保存：

```php
DB::table('webhook_events_seen')->insertOrIgnore([
    'event_id' => $event->id,
    'created_at' => now(),
]);

if (DB::table('webhook_events_seen')->where('event_id', $event->id)->exists()) {
    // 初見 → 処理する
} else {
    // 重複 → 200 OK で終了
    return response()->noContent();
}
```

### B. `subscription_results` への upsert

ハンドラが `subscription_results` を書く場合、`(fincode_subscription_id, fincode_payment_id)` をキーに upsert：

```php
SubscriptionResult::updateOrCreate(
    [
        'fincode_subscription_id' => $event->subscription_id,
        'fincode_payment_id' => $event->payment_id,
    ],
    [
        'subscription_id' => $subscription->id,
        'user_id' => $subscription->user_id,
        'status' => $event->status,
        'amount' => $event->amount,
        'charged_at_date' => $event->charged_at_date,
        'fincode_response' => $event->raw,
    ],
);
```

これは構造的に冪等：重複配信は同じ行を上書きするだけ。

## 同期フローと疎結合に保つ

Webhook ハンドラは **永続化層を共有する独立コンシューマ**として扱ってください。`SubscriptionManager.subscribe` をハンドラから呼ばないこと（あれは独自の監査・イベントセマンティクスを持つ同期フロー専用）。代わりに：

1. ハンドラは永続化を更新（`subscription_results`、必要なら `subscriptions.status`）。
2. `user_id = NULL`（システム発火）で監査ログを書く。[`../architecture/data-model.ja.md`](../architecture/data-model.ja.md) 参照。
3. ドメインイベント（例：`SubscriptionStatusChanged`）を発火 — 同期フローで使うのと同じイベントで、リスナーは 1 経路で済む。

これで、同期側と非同期側の下流効果（通知・監査ログ等）が対称になり、各側は自身の検証だけを担当できます。

## リトライ／DLQ

レスポンス：

- `200 OK`（または `204 No Content`）: 永続化完了。Fincode はリトライ停止。
- `4xx`: 永続的失敗（署名不正、ペイロード不正）。**リトライさせない**。
- `5xx`（またはそのまま例外）: 一時的失敗（DB ダウン等）。Fincode がリトライ。

処理不能イベント（未知の `subscription_id` 等）は、4xx を返してループに陥らせるのではなく **デッドレター**テーブルに退避し手動トリアージへ：

```php
DB::table('webhook_events_dead_letters')->insert([
    'event_id' => $event->id,
    'payload' => json_encode($event->raw),
    'reason' => 'unknown subscription_id',
    'created_at' => now(),
]);
return response()->noContent();
```

## ローカルテスト

Fincode は `localhost` に到達できません。トンネルを使用：

```bash
# ngrok
ngrok http 8000
# → Fincode の Webhook 設定に https://xxxx.ngrok-free.app/api/webhooks/fincode を登録
```

または **ローカルで Webhook を偽装**：

```bash
curl -X POST http://localhost:8000/api/webhooks/fincode \
  -H 'Fincode-Signature: '"$(printf '%s' "$payload" | openssl dgst -sha256 -hmac "$secret" | awk '{print $2}')" \
  -H 'Content-Type: application/json' \
  -d "$payload"
```

既知の署名でルートを駆動し、結果として `subscription_results` 行が作られることを Feature テストで検証してください。

## 次に読むもの

- [`../architecture/data-model.ja.md`](../architecture/data-model.ja.md) — `subscription_results`・`audit_logs` のスキーマ。
- [`../architecture/error-handling.ja.md`](../architecture/error-handling.ja.md) — ハンドラから `FincodeClient` を呼び戻す場合に継承する失敗モード。
- [`../getting-started/fincode-setup.ja.md`](../getting-started/fincode-setup.ja.md) — Fincode コンソールでの Webhook URL 登録。
