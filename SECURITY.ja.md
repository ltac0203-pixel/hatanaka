[English](./SECURITY.md) / 日本語

# セキュリティポリシー

本プロジェクトは決済 API と統合するアプリケーションです。脆弱性報告は重く受け止めます。

## 脆弱性の報告方法

**脆弱性に関する公開 Issue は作成しないでください。**

**GitHub Private Security Advisories** を使用してください: リポジトリの **Security** タブから Draft Advisory を作成。

報告時に含めてほしい情報：

- 問題の概要、対象ファイルパスやエンドポイント。
- 再現手順または PoC。
- 影響度の見積もり（情報漏洩、権限昇格など）。
- デフォルト構成で再現するか、設定ミスが前提か。

調査・修正・公開のタイミングについては、合理的な時間を確保いただけると助かります。

### 対応目安

| フェーズ | 目標 |
| --- | --- |
| 受領通知 | 3 営業日以内 |
| 初期評価 | 7 営業日以内 |
| 修正または緩和策の方針提示 | 重大度による |

OSS テンプレートとしての目安であり、契約上の SLA ではありません。

## サポート対象バージョン

**最新の `main` のみ**が対象です。LTS ブランチはありません。fork 利用者は upstream の修正取り込みを自身で管理してください。

## 対象外

以下は本テンプレートの脆弱性とは扱いません：

- 本番環境の設定ミスを前提とする問題（例：`APP_DEBUG=true` を本番に残しているなど）。
- アプリケーションサーバーへのボリュメトリックな DoS 攻撃。
- 上流側の Advisory がまだ存在しない依存ライブラリの問題（先に上流へ報告してください）。
- 動作する PoC を伴わない、自動スキャナの出力のみの報告。

## 本テンプレートのセキュリティ姿勢

本テンプレートはセキュリティリスクを抑えるため以下の設計判断を行っています。**fork して流用する際、以下を後退させる場合は影響を理解した上で行ってください**。

| 対策 | 場所 | 防いでいるもの |
| --- | --- | --- |
| ブラウザでカードトークン化（Fincode JS） | `resources/js/Pages/Card/*` | PAN・CVC がサーバーに到達しない。PCI スコープを縮小。 |
| ログのマスク処理 | `app/Services/Fincode/FincodeClient.php` | カード番号・CVC・トークンをログ出力前にマスク。 |
| すべての Fincode 変更系呼び出しに Idempotency-Key | `FincodeClient` | ネットワーク再試行が二重課金・二重カード登録を生まない。 |
| Circuit Breaker | `app/Services/Fincode/CircuitBreaker.php` | Fincode 障害時のカスケード失敗を防止。HTTP コネクションを抱え込まず即座に失敗させる。 |
| 監査ログ | `app/Services/AuditLogger.php`、`audit_logs` テーブル | 全変更操作の before/after 値・IP・User-Agent を記録。 |
| 状態変更を `DB::transaction()` で囲む | `SubscriptionManager` / `CardManager` | ローカル DB と Fincode 側の整合性を保ち、失敗時はローカル側をロールバック。 |
| Sanctum + ability トークン | `routes/api.php`（`ability:subscription:read` / `:write`、`ability:card:read` / `:write`） | トークン漏洩時の影響範囲を最小化。 |
| Policy | `app/Policies/SubscriptionPolicy.php`、`CardPolicy.php` | 所有権チェック（where 句に加えた多重防御）。 |
| スロットリング | `routes/api.php`（認証 `throttle:5,1`、契約・カード登録 `3,1` など） | ブルートフォース・リソース枯渇対策。 |
| セキュリティヘッダー + CSP | `app/Http/Middleware/SecurityHeaders.php`、`config/security.php` | XSS・クリックジャッキング・MIME スニッフィング。CSP 違反は `POST /api/security/csp-reports` に通知。 |
| ソフトデリート | Subscription / Card / Customer | 「削除後」も監査履歴を保持。 |

### 本テンプレートに**含まれていない**もの

- Fincode Webhook ハンドラ。実装する場合は **署名検証** と **冪等性確保** を必須にしてください。詳細は [docs/customization/webhooks.md](./docs/customization/webhooks.md)。
- エンドユーザー向け二要素認証。
- 課金失敗時の再試行（Dunning）フロー。

## 研究者の方へのお願い

検証時に以下を行わないでください：

- 他ユーザーのデータへのアクセス。
- 共有環境での破壊的なペイロード実行。
- 問題の証明に不要な範囲への横展開。
- 修正リリース前または 90 日経過前の公開（いずれか早い方）。

責任ある開示に従っていただいた報告者は、希望に応じてリリースノートに謝辞を記載します。

## ライセンス

本テンプレートは [Apache License 2.0](./LICENSE) のもとで配布されています。
