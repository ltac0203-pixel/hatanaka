[English](./index.md) / 日本語

# カスタマイズガイド

本テンプレートは **出発点**です。fork して自社プロダクトに作り変えてください。本ページでは「**変更すべき箇所**」と「**触らない方がよい箇所**」を整理します。

## 変更すべき箇所

プレースホルダのまま本番リリースしないでください。

### アイデンティティ・ブランディング

| 場所 | 内容 | 補足 |
| --- | --- | --- |
| `config/app.php` | `name` | ページタイトルやメール送信者名に影響。`.env` の `APP_NAME` でも可 |
| `package.json` | `name`・`description` | `npm` 出力や `package-lock.json` に表示 |
| `composer.json` | `name`・`description`・`keywords`・`homepage`・`support`・`authors` | 現状 `ltac0203-pixel/hatanaka` を設定。自組織の owner/repo に置換 |
| `README.md` / `README.ja.md` | クローン URL、リポジトリ参照、Copyright 行の組織名 | リポジトリで `ltac0203-pixel/hatanaka` を全文検索して自組織の owner/repo に置換 |
| `LICENSE` / `NOTICE` | 著作権者・2026 以降に fork する場合は年 | ライセンス自体は Apache-2.0 のまま |
| `resources/js/Components/ApplicationLogo.tsx` | 標準 Laravel ロゴ | 自社 SVG / PNG に置換 |
| `tailwind.config.js` | テーマカラー | ブランドパレットを定義 → 全コンポーネントが継承 |
| `resources/views/`（Blade）、`resources/js/Pages/Auth/*` | メールテンプレート、認証画面の文言 | Breeze 標準は汎用文言 |
| `lang/` | 翻訳 | 既定で `en` と `ja` のスケルトンあり |

### プランと料金

プランの正本は **Fincode 管理画面**。アプリは実行時に取得し、契約成立時に `subscriptions` 行へスナップショット保存します。

プラン追加・変更：

1. Fincode 管理画面 → 定期課金 → プラン → 新規作成（[`fincode-setup.ja.md`](../getting-started/fincode-setup.ja.md)）。
2. `plan_xxxxxx` の ID を控える。
3. （任意）フロントの説明文や並び順を `resources/js/Pages/Plan/*` で更新。

`plans` テーブルは存在しないので編集対象外です。理由は [`../architecture/data-model.ja.md`](../architecture/data-model.ja.md)。

### 機能スコープ

標準で提供されるのは：登録・ログイン・パスワードリセット・メール認証・プラン一覧・カード管理・契約／解約・決済履歴。削減・拡張の例：

| 削減対象 | 想定作業 |
| --- | --- |
| メール認証 | ルートで `email_verified_at` ミドルウェアを外す。検証コントローラを削除。`User` モデルを更新 |
| 公開登録 | `routes/api.php` の `register` と `routes/web.php` の登録ルートを制限 |
| セルフ解約 | `DELETE /api/subscription` と対応 UI を削除 |

| 拡張対象 | 着手地点 |
| --- | --- |
| 1 ユーザー複数契約 | `subscriptions_active_user_id_unique` インデックスを外し（[data-model.ja.md](../architecture/data-model.ja.md)）、`SubscriptionManager.subscribe` を更新 |
| クーポン・按分 | Fincode 側で実装し、`PlanService` でアプリに公開 |
| Webhook 駆動の Dunning | [webhooks.ja.md](./webhooks.ja.md) を参照 |

## 慎重に検討すべき箇所

「凍結」ではないものの、セキュリティ・正確性に関わる判断が埋まっています。意図的に変更してください。

### 静かに無効化しないこと

| ガード | 場所 | 外すと… |
| --- | --- | --- |
| ログのマスク処理 | `app/Services/Fincode/FincodeClient.php` | カード番号・CVC・トークンが `storage/logs/laravel.log` に出る恐れ |
| Fincode 変更系の Idempotency-Key | `FincodeClient` | ネットワーク再試行で二重課金・二重カード登録 |
| 状態変更を `DB::transaction()` で囲む | `SubscriptionManager`・`CardManager` | 部分失敗時にローカル DB と Fincode が乖離 |
| 監査ログの書き込み | Manager 内の `AuditLogger` 呼び出し | コンプライアンス対応の証跡を喪失 |
| `SecurityHeaders` ミドルウェア | `app/Http/Middleware/SecurityHeaders.php` | XSS・クリックジャッキング・MIME スニッフィングのリスク増 |
| Sanctum ability チェック | `routes/api.php`（`ability:subscription:read` 等） | トークン漏洩時の影響範囲が拡大 |
| Policy | `SubscriptionPolicy`・`CardPolicy` | 認可が「DB に存在する＝触れる」に退化 |
| スロットリング | `routes/api.php`（`throttle:5,1`・`3,1`） | ブルートフォース・濫用が容易になる |
| `subscriptions_active_user_id_unique` インデックス | マイグレーション `2026_02_21_010000` | レースで同一ユーザーの二重アクティブ契約が発生 |
| CSP report-only → 強制への移行計画 | `config/security.php` | 永遠に report-only のままだと CSP は実質的に防御していない |

### 環境に応じてチューニングする

| 設定 | 既定値 | チューニングの目安 |
| --- | --- | --- |
| `fincode.circuit_breaker.failure_threshold`（`config/fincode.php`） | 5 | トラフィック感度が高ければ下げる。経路が不安定なら上げる |
| `fincode.circuit_breaker.recovery_timeout` | 30 秒 | Fincode 障害が長期化しがちなら増やす |
| `throttle:*` の値 | ルートごと | トラフィックプロファイルが見えてから調整 |

## 触らなくてよい箇所

ただ動く足場です：

- `app/Http/Middleware/HandleInertiaRequests.php` — Inertia 共有データ。グローバルプロパティ追加時のみ。
- `bootstrap/` — Laravel のブートストラップ。理由がない限り編集しない。
- `database/migrations/0001_01_01_*` — フレームワーク標準テーブル（cache・jobs・password resets）。
- `vendor/`・`node_modules/`・`public/build/` — 生成物。コミットしない（`.gitignore` 済み）。

## 次に読むもの

- [webhooks.ja.md](./webhooks.ja.md) — Fincode Webhook ハンドラの追加（標準では未同梱）。
- [../architecture/overview.ja.md](../architecture/overview.ja.md) — どこに何があるかの全体像。
- [../operations/deployment.ja.md](../operations/deployment.ja.md) — 本番投入前のチェックリスト。
